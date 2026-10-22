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
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM payment_transactions WHERE stokvel_id = :sid',
            'SELECT c.*, c.transaction_ref AS receipt_number, p.first_name, p.last_name FROM payment_transactions c LEFT JOIN memberships sm ON sm.id = c.membership_id LEFT JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE c.stokvel_id = :sid ORDER BY c.paid_at DESC',
            ['sid' => $sid],
            $this->page($request),
            $this->perPage($request),
            url('/app/contributions')
        );

        return $this->render('stokvel/contributions/index', [
            'title' => 'Contributions',
            'activeNav' => 'contributions',
            'navContext' => 'app',
            'paginator' => $paginator,
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
        $rows = $this->db()->fetchAll(
            'SELECT mc.membership_id AS member_id, mc.stokvel_id,
                    SUM(mc.amount - mc.amount_paid) AS balance_due,
                    p.first_name, p.last_name
             FROM member_charges mc
             JOIN memberships sm ON sm.id = mc.membership_id
             JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE mc.stokvel_id = :sid AND mc.status IN (\'pending\', \'partial\', \'overdue\')
             GROUP BY mc.membership_id, mc.stokvel_id, p.first_name, p.last_name
             HAVING balance_due > 0
             ORDER BY balance_due DESC',
            ['sid' => $sid]
        );

        return $this->render('stokvel/contributions/arrears', [
            'title' => 'Arrears',
            'activeNav' => 'contributions',
            'navContext' => 'app',
            'rows' => $rows,
        ]);
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
