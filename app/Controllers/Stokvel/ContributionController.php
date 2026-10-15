<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class ContributionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('contributions.view');
        $sid = $this->requireStokvel();
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM contributions WHERE stokvel_id = :sid',
            'SELECT c.*, p.first_name, p.last_name FROM payment_transactions c LEFT JOIN memberships sm ON sm.id = c.membership_id LEFT JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE c.stokvel_id = :sid ORDER BY c.paid_at DESC',
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

        $this->service(\App\Services\Finance\ContributionService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function capture(int $stokvelId, array $data, int $userId): void
            {
                $this->db->insert(
                    'INSERT INTO contributions (stokvel_id, member_id, amount, paid_at, payment_method, reference, status, captured_by, created_at, updated_at) VALUES (:sid, :mid, :amt, :pa, :pm, :ref, :st, :by, :ca, :ua)',
                    [
                        'sid' => $stokvelId,
                        'mid' => $data['member_id'],
                        'amt' => $data['amount'],
                        'pa' => $data['paid_at'],
                        'pm' => $data['payment_method'],
                        'ref' => $data['reference'] ?: null,
                        'st' => 'posted',
                        'by' => $userId,
                        'ca' => now_utc(),
                        'ua' => now_utc(),
                    ]
                );
            }
        })->capture($sid, $data, (int) $this->userId());

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

        $count = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['member_id']) || empty($row['amount'])) {
                continue;
            }
            $this->db()->insert(
                'INSERT INTO contributions (stokvel_id, member_id, amount, paid_at, payment_method, status, captured_by, created_at, updated_at) VALUES (:sid, :mid, :amt, :pa, :pm, :st, :by, :ca, :ua)',
                [
                    'sid' => $sid,
                    'mid' => (int) $row['member_id'],
                    'amt' => $row['amount'],
                    'pa' => $row['paid_at'] ?? date('Y-m-d'),
                    'pm' => $row['payment_method'] ?? 'eft',
                    'st' => 'posted',
                    'by' => $this->userId(),
                    'ca' => now_utc(),
                    'ua' => now_utc(),
                ]
            );
            $count++;
        }

        Session::flash('success', "{$count} contributions recorded.");
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

    public function importStore(Request $request): Response
    {
        $this->requirePermission('contributions.import');
        $sid = $this->requireStokvel();
        $file = $request->file('csv');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Please upload a CSV file.');
            return $this->redirect('/app/contributions/import');
        }

        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            Session::flash('error', 'Could not read CSV.');
            return $this->redirect('/app/contributions/import');
        }

        $header = fgetcsv($handle);
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                continue;
            }
            [$memberRef, $amount, $paidAt] = $row;
            $member = $this->db()->fetch(
                'SELECT sm.id FROM memberships sm JOIN users u ON u.id = sm.user_id WHERE sm.stokvel_id = :sid AND (u.email = :ref OR sm.member_number = :ref) LIMIT 1',
                ['sid' => $sid, 'ref' => trim((string) $memberRef)]
            );
            if (!$member) {
                continue;
            }
            $this->db()->insert(
                'INSERT INTO contributions (stokvel_id, member_id, amount, paid_at, payment_method, status, captured_by, created_at, updated_at) VALUES (:sid, :mid, :amt, :pa, :pm, :st, :by, :ca, :ua)',
                [
                    'sid' => $sid,
                    'mid' => $member['id'],
                    'amt' => $amount,
                    'pa' => $paidAt ?: date('Y-m-d'),
                    'pm' => 'import',
                    'st' => 'posted',
                    'by' => $this->userId(),
                    'ca' => now_utc(),
                    'ua' => now_utc(),
                ]
            );
            $count++;
        }
        fclose($handle);

        Session::flash('success', "Imported {$count} contributions.");
        return $this->redirect('/app/contributions');
    }

    public function arrears(Request $request): Response
    {
        $this->requirePermission('contributions.view');
        $sid = $this->requireStokvel();
        $rows = $this->db()->fetchAll(
            'SELECT a.*, u.first_name, u.last_name FROM member_arrears a JOIN memberships sm ON sm.id = a.member_id JOIN users u ON u.id = sm.user_id WHERE a.stokvel_id = :sid ORDER BY a.balance_due DESC',
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
            'SELECT sm.id, p.first_name, p.last_name, u.email FROM memberships sm JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE sm.stokvel_id = :sid AND sm.status = :st ORDER BY u.last_name',
            ['sid' => $sid, 'st' => 'active']
        );
    }
}
