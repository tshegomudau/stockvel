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
use App\Services\Finance\AutoFineService;
use App\Services\Finance\FineService;
use App\Support\MoneyMath;

final class FineController extends Controller
{
    private const STATUSES = ['outstanding', 'partially_paid', 'paid', 'waived', 'cancelled'];

    public function index(Request $request): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $page = $this->page($request);
        $perPage = $this->perPage($request);

        $status = trim((string) $request->query('status', ''));
        $q = trim((string) $request->query('q', ''));
        $typeId = (int) $request->query('fine_type_id', 0);
        $membershipId = (int) $request->query('membership_id', 0);

        $where = 'f.stokvel_id = :sid';
        $params = ['sid' => $sid];
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where .= ' AND f.status = :status';
            $params['status'] = $status;
        }
        if ($typeId > 0) {
            $where .= ' AND f.fine_type_id = :ftid';
            $params['ftid'] = $typeId;
        }
        if ($membershipId > 0) {
            $where .= ' AND f.membership_id = :mid';
            $params['mid'] = $membershipId;
        }
        if ($q !== '') {
            $where .= ' AND (f.fine_type_name_snapshot LIKE :q_type OR f.reason LIKE :q_reason
                OR p.first_name LIKE :q_fn OR p.last_name LIKE :q_ln OR u.email LIKE :q_email)';
            $like = '%' . $q . '%';
            $params['q_type'] = $like;
            $params['q_reason'] = $like;
            $params['q_fn'] = $like;
            $params['q_ln'] = $like;
            $params['q_email'] = $like;
        }

        $filterQuery = array_filter([
            'status' => $status !== '' && in_array($status, self::STATUSES, true) ? $status : null,
            'fine_type_id' => $typeId > 0 ? $typeId : null,
            'membership_id' => $membershipId > 0 ? $membershipId : null,
            'q' => $q !== '' ? $q : null,
        ], static fn ($v) => $v !== null);

        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM fines f
             JOIN memberships m ON m.id = f.membership_id
             JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE ' . $where,
            'SELECT f.*,
                    TRIM(CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\'))) AS member_name,
                    u.email AS member_email
             FROM fines f
             JOIN memberships m ON m.id = f.membership_id
             JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE ' . $where . ' ORDER BY f.issued_at DESC, f.id DESC',
            $params,
            $page,
            $perPage,
            url('/app/fines'),
            $filterQuery
        );

        $types = $this->db()->fetchAll(
            'SELECT id, name FROM fine_types WHERE stokvel_id = :sid ORDER BY name ASC',
            ['sid' => $sid]
        );

        return $this->render('stokvel/fines/index', [
            'title' => 'Fines',
            'activeNav' => 'fines',
            'navContext' => 'app',
            'paginator' => $paginator,
            'filterStatus' => $status !== '' && in_array($status, self::STATUSES, true) ? $status : '',
            'filterQ' => $q,
            'filterTypeId' => $typeId,
            'filterTypes' => $types,
            'statusOptions' => self::STATUSES,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        return $this->render('stokvel/fines/create', [
            'title' => 'Issue fine',
            'activeNav' => 'fines',
            'navContext' => 'app',
            'types' => $this->activeFineTypes($sid),
            'members' => $this->activeMembers($sid),
            'preselectMembershipId' => (int) $request->query('membership_id', 0),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $data = [
            'membership_id' => (int) $request->input('membership_id', 0),
            'fine_type_id' => (int) $request->input('fine_type_id', 0),
            'amount' => (string) $request->input('amount', ''),
            'reason' => trim((string) $request->input('reason', '')),
            'due_date' => trim((string) $request->input('due_date', '')),
        ];
        $validator = Validator::make($data, [
            'membership_id' => 'required|integer',
            'fine_type_id' => 'required|integer',
            'amount' => 'required|numeric',
        ]);
        if ($validator->fails() || $data['membership_id'] < 1 || $data['fine_type_id'] < 1) {
            $this->flashOld($request);
            Session::flash('error', 'Member, fine type, and amount are required.');
            return $this->redirect('/app/fines/create');
        }

        try {
            /** @var FineService $fines */
            $fines = app()->container()->get(FineService::class);
            $id = $fines->issue(
                $sid,
                $data['membership_id'],
                $data['fine_type_id'],
                MoneyMath::normalize($data['amount']),
                (int) $this->userId(),
                [
                    'reason' => $data['reason'] !== '' ? $data['reason'] : null,
                    'due_date' => $data['due_date'] !== '' ? $data['due_date'] : null,
                ]
            );
            Session::flash('success', 'Fine issued.');
            return $this->redirect('/app/fines/' . $id);
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/fines/create');
        }
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $fine = $this->db()->fetch(
            'SELECT f.*,
                    TRIM(CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\'))) AS member_name,
                    u.email AS member_email,
                    TRIM(CONCAT(COALESCE(ip.first_name, \'\'), \' \', COALESCE(ip.last_name, \'\'))) AS issued_by_name
             FROM fines f
             JOIN memberships m ON m.id = f.membership_id
             JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             LEFT JOIN users iu ON iu.id = f.issued_by_user_id
             LEFT JOIN user_profiles ip ON ip.user_id = iu.id
             WHERE f.id = :id AND f.stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$fine) {
            Session::flash('error', 'Fine not found.');
            return $this->redirect('/app/fines');
        }

        return $this->render('stokvel/fines/show', [
            'title' => 'Fine #' . (int) $fine['id'],
            'activeNav' => 'fines',
            'navContext' => 'app',
            'fine' => $fine,
        ]);
    }

    public function waive(Request $request, string $id): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $reason = trim((string) $request->input('waived_reason', $request->input('reason', '')));
        $waivedAmount = trim((string) $request->input('waived_amount', ''));

        try {
            /** @var FineService $fines */
            $fines = app()->container()->get(FineService::class);
            $fines->waive(
                $sid,
                (int) $id,
                (int) $this->userId(),
                $reason,
                $waivedAmount !== '' ? $waivedAmount : null
            );
            Session::flash('success', 'Fine waived.');
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect('/app/fines/' . (int) $id);
    }

    public function bulkForm(Request $request): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        return $this->render('stokvel/fines/bulk', [
            'title' => 'Bulk issue fines',
            'activeNav' => 'fines',
            'navContext' => 'app',
            'types' => $this->activeFineTypes($sid),
            'members' => $this->activeMembers($sid),
            'preview' => null,
        ]);
    }

    public function bulkStore(Request $request): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $confirmed = (string) $request->input('confirm', '') === '1';
        $fineTypeId = (int) $request->input('fine_type_id', 0);
        $amount = (string) $request->input('amount', '');
        $reason = trim((string) $request->input('reason', ''));
        $dueDate = trim((string) $request->input('due_date', ''));
        $membershipIds = $request->input('membership_ids', []);
        if (!is_array($membershipIds)) {
            $membershipIds = [];
        }
        $membershipIds = array_values(array_unique(array_filter(array_map('intval', $membershipIds), static fn ($id) => $id > 0)));

        if ($fineTypeId < 1 || $membershipIds === [] || !is_numeric($amount) || (float) $amount <= 0) {
            Session::flash('error', 'Select members, a fine type, and a valid amount.');
            return $this->redirect('/app/fines/bulk');
        }

        $type = $this->db()->fetch(
            'SELECT id, name FROM fine_types WHERE id = :id AND stokvel_id = :sid AND is_active = 1 LIMIT 1',
            ['id' => $fineTypeId, 'sid' => $sid]
        );
        if (!$type) {
            Session::flash('error', 'Fine type not found.');
            return $this->redirect('/app/fines/bulk');
        }

        $members = $this->db()->fetchAll(
            'SELECT m.id,
                    TRIM(CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\'))) AS member_name,
                    u.email
             FROM memberships m
             JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE m.stokvel_id = :sid AND m.id IN (' . implode(',', array_map('intval', $membershipIds)) . ')
             ORDER BY p.last_name ASC, p.first_name ASC',
            ['sid' => $sid]
        );

        if (!$confirmed) {
            return $this->render('stokvel/fines/bulk', [
                'title' => 'Confirm bulk fines',
                'activeNav' => 'fines',
                'navContext' => 'app',
                'types' => $this->activeFineTypes($sid),
                'members' => $this->activeMembers($sid),
                'preview' => [
                    'fine_type_id' => $fineTypeId,
                    'fine_type_name' => (string) $type['name'],
                    'amount' => MoneyMath::normalize($amount),
                    'reason' => $reason,
                    'due_date' => $dueDate,
                    'members' => $members,
                    'membership_ids' => array_map(static fn ($m) => (int) $m['id'], $members),
                ],
            ]);
        }

        $idempotencyKey = 'bulk-fine-' . bin2hex(random_bytes(8));
        try {
            /** @var FineService $fines */
            $fines = app()->container()->get(FineService::class);
            $ids = $fines->bulkIssue(
                $sid,
                array_map(static fn ($m) => (int) $m['id'], $members),
                $fineTypeId,
                MoneyMath::normalize($amount),
                (int) $this->userId(),
                [
                    'reason' => $reason !== '' ? $reason : null,
                    'due_date' => $dueDate !== '' ? $dueDate : null,
                    'idempotency_key' => $idempotencyKey,
                ]
            );
            Session::flash('success', count($ids) . ' fine(s) issued.');
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/fines/bulk');
        }

        return $this->redirect('/app/fines');
    }

    public function autoForm(Request $request): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $this->ensureStarterAutoFineTypes($sid);

        $rule = trim((string) $request->query('rule', AutoFineService::RULE_LATE_CONTRIBUTION));
        /** @var AutoFineService $auto */
        $auto = app()->container()->get(AutoFineService::class);
        $preview = $auto->preview($sid, $rule);

        return $this->render('stokvel/fines/auto', [
            'title' => 'Automatic fines',
            'activeNav' => 'fines',
            'navContext' => 'app',
            'rule' => $preview['rule'],
            'preview' => $preview,
            'rules' => [
                AutoFineService::RULE_LATE_CONTRIBUTION => 'Late contribution (after grace period)',
                AutoFineService::RULE_MISSED_MEETING => 'Missed meeting (finalised attendance, absent, no apology)',
            ],
        ]);
    }

    public function autoApply(Request $request): Response
    {
        $this->requirePermission('fines.manage');
        $sid = $this->requireStokvel();
        $rule = trim((string) $request->input('rule', ''));
        $confirmed = (string) $request->input('confirm', '') === '1';

        if (!$confirmed) {
            Session::flash('error', 'Confirm the preview before applying automatic fines.');
            return $this->redirect('/app/fines/auto?rule=' . rawurlencode($rule));
        }

        try {
            /** @var AutoFineService $auto */
            $auto = app()->container()->get(AutoFineService::class);
            $result = $auto->apply($sid, $rule, (int) $this->userId());
            Session::flash(
                'success',
                $result['issued'] . ' automatic fine(s) issued'
                . ($result['skipped'] > 0 ? ' (' . $result['skipped'] . ' skipped).' : '.')
            );
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/fines/auto?rule=' . rawurlencode($rule));
        }

        return $this->redirect('/app/fines');
    }

    /** Seed starter automatic fine types if missing (tenant-owned, editable). */
    private function ensureStarterAutoFineTypes(int $stokvelId): void
    {
        $starters = [
            ['code' => 'late_contribution', 'name' => 'Late contribution', 'amount' => '50.00', 'auto' => 1],
            ['code' => 'missed_meeting', 'name' => 'Missed meeting', 'amount' => '50.00', 'auto' => 1],
            ['code' => 'late_arrival', 'name' => 'Late arrival', 'amount' => '20.00', 'auto' => 0],
        ];
        $now = now_utc();
        foreach ($starters as $i => $s) {
            $exists = $this->db()->fetch(
                'SELECT id FROM fine_types WHERE stokvel_id = :sid AND code = :code LIMIT 1',
                ['sid' => $stokvelId, 'code' => $s['code']]
            );
            if ($exists) {
                continue;
            }
            $this->db()->insert(
                'INSERT INTO fine_types
                    (stokvel_id, code, name, description, default_amount, is_automatic_eligible,
                     display_order, is_active, created_at, updated_at)
                 VALUES
                    (:sid, :code, :name, :desc, :amt, :auto, :ord, 1, :ca, :ua)',
                [
                    'sid' => $stokvelId,
                    'code' => $s['code'],
                    'name' => $s['name'],
                    'desc' => 'Starter fine type — edit or deactivate as needed.',
                    'amt' => $s['amount'],
                    'auto' => $s['auto'],
                    'ord' => $i + 1,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function activeFineTypes(int $stokvelId): array
    {
        return $this->db()->fetchAll(
            'SELECT id, name, default_amount FROM fine_types
             WHERE stokvel_id = :sid AND is_active = 1 ORDER BY name ASC',
            ['sid' => $stokvelId]
        );
    }

    /** @return list<array<string, mixed>> */
    private function activeMembers(int $stokvelId): array
    {
        return $this->db()->fetchAll(
            'SELECT m.id,
                    TRIM(CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\'))) AS member_name,
                    u.email
             FROM memberships m
             JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE m.stokvel_id = :sid AND m.status IN (\'active\', \'suspended\')
             ORDER BY p.last_name ASC, p.first_name ASC',
            ['sid' => $stokvelId]
        );
    }
}
