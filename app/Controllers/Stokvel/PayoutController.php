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
use App\Services\Payouts\PayoutService;

final class PayoutController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM payout_cycles WHERE stokvel_id = :sid',
            'SELECT * FROM payout_cycles WHERE stokvel_id = :sid ORDER BY created_at DESC',
            ['sid' => $sid],
            $page,
            $perPage,
            url('/app/payouts')
        );
        return $this->render('stokvel/payouts/index', [
            'title' => 'Payouts',
            'activeNav' => 'payouts',
            'navContext' => 'app',
            'paginator' => $paginator,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $members = $this->db()->fetchAll(
            'SELECT sm.id, p.first_name, p.last_name FROM memberships sm
             JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE sm.stokvel_id = :sid AND sm.status = :st
             ORDER BY p.last_name, p.first_name',
            ['sid' => $sid, 'st' => 'active']
        );
        return $this->render('stokvel/payouts/create', [
            'title' => 'New Payout',
            'activeNav' => 'payouts',
            'navContext' => 'app',
            'members' => $members,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $data = [
            'name' => trim((string) $request->input('name', 'Payout cycle')),
            'member_id' => (int) $request->input('member_id', 0),
            'amount' => (string) $request->input('amount', ''),
            'scheduled_at' => (string) $request->input('scheduled_at', date('Y-m-d')),
        ];
        $validator = Validator::make($data, [
            'name' => 'required|string',
            'member_id' => 'required|integer',
            'amount' => 'required|numeric',
            'scheduled_at' => 'required|date',
        ]);
        if ($validator->fails() || $data['member_id'] < 1) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid payout details.');
            return $this->redirect('/app/payouts/create');
        }

        try {
            /** @var PayoutService $payouts */
            $payouts = app()->container()->get(PayoutService::class);
            $cycleId = $payouts->createRotationCycle(
                $sid,
                $data['name'],
                $data['scheduled_at'],
                null,
                (int) $this->userId()
            );
            $payouts->scheduleMemberPayout(
                $sid,
                $cycleId,
                $data['member_id'],
                $data['amount'],
                $data['scheduled_at']
            );
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/payouts/create');
        }

        Session::flash('success', 'Payout cycle saved.');
        return $this->redirect('/app/payouts');
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch(
            'SELECT * FROM payout_cycles WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$row) {
            return $this->redirect('/app/payouts');
        }
        $schedule = $this->db()->fetchAll(
            'SELECT ps.*, p.first_name, p.last_name FROM payout_schedule ps
             INNER JOIN memberships m ON m.id = ps.membership_id
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE ps.stokvel_id = :sid AND ps.payout_cycle_id = :cid
             ORDER BY ps.sequence_number ASC',
            ['sid' => $sid, 'cid' => (int) $id]
        );
        return $this->render('stokvel/payouts/show', [
            'title' => 'Payout cycle #' . $id,
            'activeNav' => 'payouts',
            'navContext' => 'app',
            'item' => $row,
            'schedule' => $schedule,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch(
            'SELECT * FROM payout_cycles WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$row) {
            return $this->redirect('/app/payouts');
        }
        return $this->render('stokvel/payouts/edit', [
            'title' => 'Edit Payout cycle',
            'activeNav' => 'payouts',
            'navContext' => 'app',
            'item' => $row,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $name = trim((string) $request->input('name', ''));
        $amount = $request->input('payout_amount');
        if ($name === '') {
            Session::flash('error', 'Name is required.');
            return $this->redirect('/app/payouts/' . $id . '/edit');
        }
        $this->db()->exec(
            'UPDATE payout_cycles SET name = :name, payout_amount = :amount, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            [
                'name' => $name,
                'amount' => $amount !== null && $amount !== '' ? $amount : null,
                'ua' => now_utc(),
                'id' => (int) $id,
                'sid' => $sid,
            ]
        );
        Session::flash('success', 'Payout cycle updated.');
        return $this->redirect('/app/payouts/' . $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('payouts.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE payout_cycles SET status = :status, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['status' => 'cancelled', 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Payout cycle cancelled.');
        return $this->redirect('/app/payouts');
    }
}
