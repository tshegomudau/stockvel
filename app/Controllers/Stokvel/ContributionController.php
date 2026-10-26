<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Exceptions\FinanceException;
use App\Services\Finance\ChartOfAccountsService;
use App\Services\Finance\ContributionService;
use App\Services\Finance\CsvImportService;

final class ContributionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('contributions.view');
        $sid = $this->requireStokvel();

        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));
        $allowedStatuses = ['completed', 'pending', 'failed', 'reversed'];

        $where = 'c.stokvel_id = :sid';
        $params = ['sid' => $sid];
        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $where .= ' AND c.status = :status';
            $params['status'] = $status;
        }
        if ($q !== '') {
            $where .= ' AND (p.first_name LIKE :q_fn OR p.last_name LIKE :q_ln
                OR CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\')) LIKE :q_full)';
            $like = '%' . $q . '%';
            $params['q_fn'] = $like;
            $params['q_ln'] = $like;
            $params['q_full'] = $like;
        }

        $filterQuery = array_filter([
            'status' => $status !== '' && in_array($status, $allowedStatuses, true) ? $status : null,
            'q' => $q !== '' ? $q : null,
        ], static fn ($v) => $v !== null);

        $joins = 'LEFT JOIN memberships sm ON sm.id = c.membership_id
             LEFT JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id';

        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM payment_transactions c ' . $joins . ' WHERE ' . $where,
            'SELECT c.*, c.transaction_ref AS receipt_number, p.first_name, p.last_name
             FROM payment_transactions c
             ' . $joins . '
             WHERE ' . $where . '
             ORDER BY c.paid_at DESC',
            $params,
            $this->page($request),
            $this->perPage($request),
            url('/app/contributions'),
            $filterQuery
        );

        $metrics = $this->monthContributionMetrics($sid);

        return $this->render('stokvel/contributions/index', [
            'title' => 'Contributions',
            'activeNav' => 'contributions',
            'navContext' => 'app',
            'paginator' => $paginator,
            'filterStatus' => $status !== '' && in_array($status, $allowedStatuses, true) ? $status : '',
            'filterQ' => $q,
            'statusOptions' => $allowedStatuses,
            'metrics' => $metrics,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('contributions.capture');
        $members = $this->activeMembers();
        return $this->render('stokvel/contributions/create', [
            'title' => 'Capture contribution',
            'activeNav' => 'contributions',
            'navContext' => 'app',
            'members' => $members,
            'paymentMethods' => ContributionService::PAYMENT_METHODS,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('contributions.capture');
        $sid = $this->requireStokvel();
        $data = [
            'member_id' => (int) $request->input('member_id'),
            'amount' => (string) $request->input('amount'),
            'paid_at' => (string) $request->input('paid_at', date('Y-m-d')),
            'payment_method' => (string) $request->input('payment_method', 'cash'),
            'reference' => trim((string) $request->input('reference', '')),
        ];
        $validator = Validator::make($data, [
            'member_id' => 'required|integer',
            'amount' => 'required|numeric',
            'paid_at' => 'required|date',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid contribution details.');
            return $this->redirect('/app/contributions/create');
        }

        try {
            app()->container()->get(ChartOfAccountsService::class)->ensureDefaultAccounts($sid);
            $this->contributions()->capturePayment(
                $sid,
                $data['member_id'],
                $data['amount'],
                $data['payment_method'],
                (int) $this->userId(),
                null,
                $data['reference'] !== '' ? $data['reference'] : null,
                $data['paid_at']
            );
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/contributions/create');
        }

        Session::flash('success', 'Contribution recorded.');
        return $this->redirect('/app/contributions');
    }

    public function bulkForm(Request $request): Response
    {
        $this->requirePermission('contributions.capture');
        return $this->render('stokvel/contributions/bulk', [
            'title' => 'Bulk capture',
            'activeNav' => 'contributions',
            'navContext' => 'app',
            'members' => $this->activeMembers(),
            'paymentMethods' => ContributionService::PAYMENT_METHODS,
        ]);
    }

    public function bulkStore(Request $request): Response
    {
        $this->requirePermission('contributions.capture');
        $sid = $this->requireStokvel();
        $rows = $request->input('rows', []);
        if (!is_array($rows)) {
            Session::flash('error', 'No rows submitted.');
            return $this->redirect('/app/contributions/bulk');
        }

        $payload = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['member_id']) || empty($row['amount'])) {
                continue;
            }
            $payload[] = [
                'member_id' => (int) $row['member_id'],
                'amount' => (string) $row['amount'],
                'payment_method' => (string) ($row['payment_method'] ?? 'eft'),
                'reference' => isset($row['reference']) ? (string) $row['reference'] : null,
            ];
        }

        if ($payload === []) {
            Session::flash('error', 'No valid rows submitted.');
            return $this->redirect('/app/contributions/bulk');
        }

        try {
            app()->container()->get(ChartOfAccountsService::class)->ensureDefaultAccounts($sid);
            $results = $this->contributions()->captureBulk(
                $sid,
                $payload,
                (int) $this->userId(),
                'eft',
                'bulk-' . $sid . '-' . hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))
            );
            Session::flash('success', count($results) . ' contributions recorded.');
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect('/app/contributions');
    }

    public function importForm(Request $request): Response
    {
        $this->requirePermission('contributions.import');
        return $this->render('stokvel/contributions/import', [
            'title' => 'Import CSV',
            'activeNav' => 'contributions',
            'navContext' => 'app',
        ]);
    }

    public function importTemplate(Request $request): Response
    {
        $this->requirePermission('contributions.import');
        /** @var CsvImportService $csv */
        $csv = app()->container()->get(CsvImportService::class);
        return new Response($csv->templateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="contributions-import-template.csv"',
        ]);
    }

    public function importStore(Request $request): Response
    {
        $this->requirePermission('contributions.import');
        $sid = $this->requireStokvel();
        $file = $request->file('csv');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Please upload a CSV file.');
            return $this->redirect('/app/contributions/import');
        }

        $content = file_get_contents((string) $file['tmp_name']);
        if ($content === false || trim($content) === '') {
            Session::flash('error', 'Could not read the CSV file.');
            return $this->redirect('/app/contributions/import');
        }

        try {
            /** @var CsvImportService $importer */
            $importer = app()->container()->get(CsvImportService::class);
            $result = $importer->import(
                $sid,
                $content,
                (int) $this->userId(),
                'bulk-csv-' . $sid . '-' . hash('sha256', $content)
            );
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/contributions/import');
        }

        if ($result['errors'] !== []) {
            Session::flash('error', implode(' ', array_slice($result['errors'], 0, 8)));
        }
        Session::flash(
            'success',
            $result['imported'] . ' contribution(s) imported'
            . ($result['skipped'] > 0 ? ' (' . $result['skipped'] . ' row(s) skipped).' : '.')
        );
        return $this->redirect($result['imported'] > 0 ? '/app/contributions' : '/app/contributions/import');
    }

    public function arrears(Request $request): Response
    {
        $this->requirePermission('contributions.view');
        $sid = $this->requireStokvel();
        $ageing = trim((string) $request->query('ageing', ''));
        $minDays = match ($ageing) {
            '30' => 30,
            '60' => 60,
            '90' => 90,
            default => 0,
        };

        $params = ['sid' => $sid];
        $havingAge = '';
        if ($minDays > 0) {
            $havingAge = ' AND DATEDIFF(UTC_DATE(), MIN(mc.due_date)) >= :min_days';
            $params['min_days'] = $minDays;
        }

        $rows = $this->db()->fetchAll(
            'SELECT mc.membership_id AS member_id, mc.stokvel_id,
                    SUM(mc.amount - mc.amount_paid) AS balance_due,
                    COUNT(*) AS owing_count,
                    MIN(mc.due_date) AS oldest_due,
                    DATEDIFF(UTC_DATE(), MIN(mc.due_date)) AS days_overdue,
                    MAX(pt.paid_at) AS last_contribution,
                    p.first_name, p.last_name
             FROM member_charges mc
             JOIN memberships sm ON sm.id = mc.membership_id
             JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             LEFT JOIN payment_transactions pt ON pt.membership_id = mc.membership_id
                AND pt.stokvel_id = mc.stokvel_id
                AND pt.status IN (\'posted\',\'completed\',\'allocated\',\'confirmed\')
             WHERE mc.stokvel_id = :sid AND mc.status IN (\'pending\', \'partial\', \'overdue\')
             GROUP BY mc.membership_id, mc.stokvel_id, p.first_name, p.last_name
             HAVING balance_due > 0' . $havingAge . '
             ORDER BY balance_due DESC',
            $params
        );

        return $this->render('stokvel/contributions/arrears', [
            'title' => 'Arrears',
            'activeNav' => 'contributions',
            'navContext' => 'app',
            'rows' => $rows,
            'ageing' => $ageing,
            'canCapture' => $this->tenant()->can('contributions.capture'),
        ]);
    }

    public function arrearsCaptureForm(Request $request): Response
    {
        $this->requirePermission('contributions.capture');
        return $this->render('stokvel/contributions/arrears-capture', [
            'title' => 'Capture arrears',
            'activeNav' => 'contributions',
            'navContext' => 'app',
            'members' => $this->activeMembers(),
        ]);
    }

    public function arrearsCaptureStore(Request $request): Response
    {
        $this->requirePermission('contributions.capture');
        $sid = $this->requireStokvel();
        $data = [
            'member_id' => (int) $request->input('member_id'),
            'amount' => (string) $request->input('amount'),
            'due_date' => (string) $request->input('due_date', date('Y-m-d')),
            'description' => trim((string) $request->input('description', 'Historical arrears')),
            'period_label' => trim((string) $request->input('period_label', '')),
        ];
        $validator = Validator::make($data, [
            'member_id' => 'required|integer',
            'amount' => 'required|numeric',
            'due_date' => 'required|date',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid arrears details.');
            return $this->redirect('/app/contributions/arrears/capture');
        }

        try {
            $this->charges()->createManualArrear(
                $sid,
                $data['member_id'],
                $data['amount'],
                $data['due_date'],
                $data['description'] !== '' ? $data['description'] : 'Historical arrears',
                $data['period_label'] !== '' ? $data['period_label'] : null
            );
            Session::flash('success', 'Arrears captured.');
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/contributions/arrears/capture');
        }

        return $this->redirect('/app/contributions/arrears');
    }

    public function arrearsImportForm(Request $request): Response
    {
        $this->requirePermission('contributions.capture');
        return $this->render('stokvel/contributions/arrears-import', [
            'title' => 'Import arrears',
            'activeNav' => 'contributions',
            'navContext' => 'app',
        ]);
    }

    public function arrearsImportTemplate(Request $request): Response
    {
        $this->requirePermission('contributions.capture');
        $csv = "email,amount,due_date,description,period_label\n"
            . "member@example.com,500.00,2024-06-01,Historical arrears,2024-06\n";
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="arrears-import-template.csv"',
        ]);
    }

    public function arrearsImportStore(Request $request): Response
    {
        $this->requirePermission('contributions.capture');
        $sid = $this->requireStokvel();
        $file = $request->file('csv');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Please upload a CSV file.');
            return $this->redirect('/app/contributions/arrears/import');
        }
        $content = file_get_contents((string) $file['tmp_name']);
        if ($content === false || trim($content) === '') {
            Session::flash('error', 'Could not read the CSV file.');
            return $this->redirect('/app/contributions/arrears/import');
        }

        $lines = preg_split('/\R/', trim($content)) ?: [];
        $header = array_map(
            static fn ($h) => strtolower(trim((string) $h)),
            str_getcsv(array_shift($lines) ?? '')
        );
        $imported = 0;
        $errors = [];
        $charges = $this->charges();
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $row = [];
            foreach ($header as $idx => $key) {
                $row[$key] = trim((string) ($cols[$idx] ?? ''));
            }
            $email = strtolower($row['email'] ?? '');
            if ($email === '' || ($row['amount'] ?? '') === '') {
                $errors[] = 'Row ' . ($i + 2) . ': email and amount are required.';
                continue;
            }
            $member = $this->db()->fetch(
                'SELECT sm.id FROM memberships sm
                 JOIN users u ON u.id = sm.user_id
                 WHERE sm.stokvel_id = :sid AND u.email = :email LIMIT 1',
                ['sid' => $sid, 'email' => $email]
            );
            if (!$member) {
                $errors[] = 'Row ' . ($i + 2) . ': member not found for ' . $email;
                continue;
            }
            try {
                $charges->createManualArrear(
                    $sid,
                    (int) $member['id'],
                    (string) $row['amount'],
                    ($row['due_date'] ?? '') !== '' ? $row['due_date'] : date('Y-m-d'),
                    ($row['description'] ?? '') !== '' ? $row['description'] : 'Historical arrears',
                    ($row['period_label'] ?? '') !== '' ? $row['period_label'] : null
                );
                $imported++;
            } catch (\Throwable $e) {
                $errors[] = 'Row ' . ($i + 2) . ': ' . $e->getMessage();
            }
        }

        if ($errors !== []) {
            Session::flash('error', implode(' ', array_slice($errors, 0, 6)));
        }
        Session::flash('success', $imported . ' arrears row(s) imported.');
        return $this->redirect($imported > 0 ? '/app/contributions/arrears' : '/app/contributions/arrears/import');
    }

    /**
     * Real month-to-date contribution KPIs from obligations and payments.
     *
     * @return array<string, float|int>
     */
    private function monthContributionMetrics(int $stokvelId): array
    {
        $db = $this->db();
        $expected = (float) ($db->fetch(
            "SELECT COALESCE(SUM(amount),0) AS t FROM member_charges
             WHERE stokvel_id = :sid
               AND MONTH(due_date) = MONTH(UTC_TIMESTAMP())
               AND YEAR(due_date) = YEAR(UTC_TIMESTAMP())",
            ['sid' => $stokvelId]
        )['t'] ?? 0);
        $collected = (float) ($db->fetch(
            "SELECT COALESCE(SUM(amount),0) AS t FROM payment_transactions
             WHERE stokvel_id = :sid
               AND status IN ('posted','completed','allocated','confirmed')
               AND MONTH(paid_at) = MONTH(UTC_TIMESTAMP())
               AND YEAR(paid_at) = YEAR(UTC_TIMESTAMP())",
            ['sid' => $stokvelId]
        )['t'] ?? 0);
        $outstanding = (float) ($db->fetch(
            "SELECT COALESCE(SUM(amount - amount_paid),0) AS t FROM member_charges
             WHERE stokvel_id = :sid
               AND status IN ('pending','partial','overdue')
               AND MONTH(due_date) = MONTH(UTC_TIMESTAMP())
               AND YEAR(due_date) = YEAR(UTC_TIMESTAMP())",
            ['sid' => $stokvelId]
        )['t'] ?? 0);
        $overdue = (float) ($db->fetch(
            "SELECT COALESCE(SUM(amount - amount_paid),0) AS t FROM member_charges
             WHERE stokvel_id = :sid
               AND status IN ('pending','partial','overdue')
               AND due_date < UTC_DATE()",
            ['sid' => $stokvelId]
        )['t'] ?? 0);
        $adminFees = (float) ($db->fetch(
            "SELECT COALESCE(SUM(amount_paid),0) AS t FROM member_charges
             WHERE stokvel_id = :sid
               AND (charge_code LIKE 'admin%' OR description LIKE '%admin%')
               AND MONTH(due_date) = MONTH(UTC_TIMESTAMP())
               AND YEAR(due_date) = YEAR(UTC_TIMESTAMP())",
            ['sid' => $stokvelId]
        )['t'] ?? 0);
        $fullyPaid = (int) ($db->fetch(
            "SELECT COUNT(*) AS c FROM (
                SELECT membership_id
                FROM member_charges
                WHERE stokvel_id = :sid
                  AND MONTH(due_date) = MONTH(UTC_TIMESTAMP())
                  AND YEAR(due_date) = YEAR(UTC_TIMESTAMP())
                GROUP BY membership_id
                HAVING SUM(amount - amount_paid) <= 0.009
             ) x",
            ['sid' => $stokvelId]
        )['c'] ?? 0);
        $inArrears = (int) ($db->fetch(
            "SELECT COUNT(DISTINCT membership_id) AS c FROM member_charges
             WHERE stokvel_id = :sid AND status IN ('pending','partial','overdue')
               AND (amount - amount_paid) > 0",
            ['sid' => $stokvelId]
        )['c'] ?? 0);
        $pct = $expected > 0 ? round(($collected / $expected) * 100, 1) : 0.0;

        return [
            'expected' => $expected,
            'collected' => $collected,
            'outstanding' => $outstanding,
            'collection_pct' => $pct,
            'fully_paid' => $fullyPaid,
            'in_arrears' => $inArrears,
            'admin_fees' => $adminFees,
            'overdue' => $overdue,
        ];
    }

    private function charges(): \App\Services\Finance\ChargeService
    {
        return app()->container()->get(\App\Services\Finance\ChargeService::class);
    }

    private function activeMembers(): array
    {
        $sid = $this->requireStokvel();
        return $this->db()->fetchAll(
            'SELECT sm.id, p.first_name, p.last_name, u.email FROM memberships sm JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE sm.stokvel_id = :sid AND sm.status = :st ORDER BY p.last_name, p.first_name',
            ['sid' => $sid, 'st' => 'active']
        );
    }

    private function contributions(): ContributionService
    {
        /** @var ContributionService */
        return $this->service(ContributionService::class, fn ($db) => new ContributionService(
            $db,
            new \App\Services\Finance\ChargeService($db, new \App\Services\Finance\FeeStructureService($db)),
            new \App\Services\Finance\LedgerService($db),
            new \App\Services\Finance\ChartOfAccountsService($db)
        ));
    }
}
