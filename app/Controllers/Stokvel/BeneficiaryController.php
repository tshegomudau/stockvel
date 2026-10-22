<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AuditService;
use App\Support\SensitiveData;

final class BeneficiaryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('beneficiaries.view');
        $sid = $this->requireStokvel();

        $status = trim((string) $request->query('status', ''));
        $memberId = (int) $request->query('member_id', 0);
        $q = trim((string) $request->query('q', ''));

        $where = 'b.stokvel_id = :sid';
        $params = ['sid' => $sid];
        if ($status !== '' && in_array($status, ['active', 'inactive'], true)) {
            $where .= ' AND b.status = :status';
            $params['status'] = $status;
        }
        if ($memberId > 0) {
            $where .= ' AND b.membership_id = :mid';
            $params['mid'] = $memberId;
        }
        if ($q !== '') {
            $where .= ' AND (b.full_name LIKE :q_name OR b.relationship LIKE :q_rel)';
            $like = '%' . $q . '%';
            $params['q_name'] = $like;
            $params['q_rel'] = $like;
        }

        $filterQuery = array_filter([
            'status' => $status !== '' ? $status : null,
            'member_id' => $memberId > 0 ? $memberId : null,
            'q' => $q !== '' ? $q : null,
        ], static fn ($v) => $v !== null);

        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM beneficiaries b WHERE ' . $where,
            'SELECT b.*, p.first_name, p.last_name, sm.member_number
             FROM beneficiaries b
             INNER JOIN memberships sm ON sm.id = b.membership_id AND sm.stokvel_id = b.stokvel_id
             INNER JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE ' . $where . '
             ORDER BY p.last_name, p.first_name, b.full_name ASC',
            $params,
            $page,
            $perPage,
            url('/app/beneficiaries'),
            $filterQuery
        );

        return $this->render('stokvel/beneficiaries/index', [
            'title' => 'Beneficiaries',
            'activeNav' => 'beneficiaries',
            'navContext' => 'app',
            'paginator' => $paginator,
            'members' => $this->loadMembers($sid),
            'filterStatus' => $status,
            'filterMemberId' => $memberId,
            'filterQ' => $q,
            'canManage' => $this->tenant()->can('beneficiaries.manage'),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('beneficiaries.manage');
        $sid = $this->requireStokvel();
        $preselectMember = (int) $request->query('member_id', 0);

        return $this->render('stokvel/beneficiaries/create', [
            'title' => 'Add beneficiary',
            'activeNav' => 'beneficiaries',
            'navContext' => 'app',
            'members' => $this->loadMembers($sid),
            'preselectMemberId' => $preselectMember,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('beneficiaries.manage');
        $sid = $this->requireStokvel();

        $data = $this->collectInput($request);
        $validator = Validator::make($data, [
            'membership_id' => 'required|integer',
            'full_name' => 'required|min:2|max:200',
            'relationship' => 'required|min:2|max:64',
            'share_percent' => 'required|numeric|min:0|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|max:32',
            'id_number' => 'nullable|max:13',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            $this->flashErrors($validator->errors());
            Session::flash('error', 'Please correct the beneficiary details.');
            return $this->redirect('/app/beneficiaries/create');
        }

        $membership = $this->loadMembership((int) $data['membership_id'], $sid);
        if ($membership === null) {
            $this->flashOld($request);
            Session::flash('error', 'Selected member was not found in this stokvel.');
            return $this->redirect('/app/beneficiaries/create');
        }

        try {
            $this->assertShareTotal($sid, (int) $data['membership_id'], (float) $data['share_percent'], null);
        } catch (\RuntimeException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/beneficiaries/create');
        }

        $now = now_utc();
        $idNumber = $this->maskedIdNumber((string) $data['id_number']);
        $isPrimary = !empty($data['is_primary']) ? 1 : 0;

        if ($isPrimary === 1) {
            $this->clearPrimary((int) $data['membership_id'], $sid);
        }

        $id = (int) $this->db()->insert(
            'INSERT INTO beneficiaries
                (stokvel_id, membership_id, full_name, id_number, relationship, share_percent, phone, email, is_primary, status, created_at, updated_at)
             VALUES
                (:sid, :mid, :name, :idn, :rel, :share, :phone, :email, :primary, :status, :ca, :ua)',
            [
                'sid' => $sid,
                'mid' => (int) $data['membership_id'],
                'name' => $data['full_name'],
                'idn' => $idNumber,
                'rel' => $data['relationship'],
                'share' => (float) $data['share_percent'],
                'phone' => $this->nullable($data['phone']),
                'email' => $this->nullable($data['email']),
                'primary' => $isPrimary,
                'status' => 'active',
                'ca' => $now,
                'ua' => $now,
            ]
        );

        $this->audit()->log(
            'beneficiary.created',
            'beneficiary',
            $id,
            null,
            [
                'membership_id' => (int) $data['membership_id'],
                'full_name' => $data['full_name'],
                'relationship' => $data['relationship'],
                'share_percent' => (float) $data['share_percent'],
                'is_primary' => $isPrimary,
                'status' => 'active',
            ],
            ['user_id' => $this->userId()],
            $sid,
            $this->userId()
        );

        Session::flash('success', 'Beneficiary added.');
        return $this->redirect('/app/beneficiaries/' . $id);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('beneficiaries.view');
        $sid = $this->requireStokvel();
        $row = $this->loadBeneficiary((int) $id, $sid);
        if ($row === null) {
            Session::flash('error', 'Beneficiary not found.');
            return $this->redirect('/app/beneficiaries');
        }

        return $this->render('stokvel/beneficiaries/show', [
            'title' => $row['full_name'] ?? 'Beneficiary',
            'activeNav' => 'beneficiaries',
            'navContext' => 'app',
            'item' => $row,
            'canManage' => $this->tenant()->can('beneficiaries.manage'),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('beneficiaries.manage');
        $sid = $this->requireStokvel();
        $row = $this->loadBeneficiary((int) $id, $sid);
        if ($row === null) {
            Session::flash('error', 'Beneficiary not found.');
            return $this->redirect('/app/beneficiaries');
        }

        return $this->render('stokvel/beneficiaries/edit', [
            'title' => 'Edit beneficiary',
            'activeNav' => 'beneficiaries',
            'navContext' => 'app',
            'item' => $row,
            'members' => $this->loadMembers($sid),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('beneficiaries.manage');
        $sid = $this->requireStokvel();
        $existing = $this->loadBeneficiary((int) $id, $sid);
        if ($existing === null) {
            Session::flash('error', 'Beneficiary not found.');
            return $this->redirect('/app/beneficiaries');
        }

        $data = $this->collectInput($request);
        $validator = Validator::make($data, [
            'membership_id' => 'required|integer',
            'full_name' => 'required|min:2|max:200',
            'relationship' => 'required|min:2|max:64',
            'share_percent' => 'required|numeric|min:0|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|max:32',
            'id_number' => 'nullable|max:13',
            'status' => 'nullable|in:active,inactive',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            $this->flashErrors($validator->errors());
            Session::flash('error', 'Please correct the beneficiary details.');
            return $this->redirect('/app/beneficiaries/' . $id . '/edit');
        }

        $membership = $this->loadMembership((int) $data['membership_id'], $sid);
        if ($membership === null) {
            $this->flashOld($request);
            Session::flash('error', 'Selected member was not found in this stokvel.');
            return $this->redirect('/app/beneficiaries/' . $id . '/edit');
        }

        $status = in_array($data['status'], ['active', 'inactive'], true)
            ? $data['status']
            : (string) ($existing['status'] ?? 'active');

        if ($status === 'active') {
            try {
                $this->assertShareTotal($sid, (int) $data['membership_id'], (float) $data['share_percent'], (int) $id);
            } catch (\RuntimeException $e) {
                $this->flashOld($request);
                Session::flash('error', $e->getMessage());
                return $this->redirect('/app/beneficiaries/' . $id . '/edit');
            }
        }

        $isPrimary = !empty($data['is_primary']) ? 1 : 0;
        $idNumberInput = trim((string) $data['id_number']);
        $idNumber = $idNumberInput !== ''
            ? $this->maskedIdNumber($idNumberInput)
            : ($existing['id_number'] ?? null);

        if ($isPrimary === 1) {
            $this->clearPrimary((int) $data['membership_id'], $sid, (int) $id);
        }

        $this->db()->exec(
            'UPDATE beneficiaries SET
                membership_id = :mid,
                full_name = :name,
                id_number = :idn,
                relationship = :rel,
                share_percent = :share,
                phone = :phone,
                email = :email,
                is_primary = :primary,
                status = :status,
                updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            [
                'mid' => (int) $data['membership_id'],
                'name' => $data['full_name'],
                'idn' => $idNumber,
                'rel' => $data['relationship'],
                'share' => (float) $data['share_percent'],
                'phone' => $this->nullable($data['phone']),
                'email' => $this->nullable($data['email']),
                'primary' => $isPrimary,
                'status' => $status,
                'ua' => now_utc(),
                'id' => (int) $id,
                'sid' => $sid,
            ]
        );

        $this->audit()->log(
            'beneficiary.updated',
            'beneficiary',
            (int) $id,
            [
                'full_name' => $existing['full_name'] ?? null,
                'relationship' => $existing['relationship'] ?? null,
                'share_percent' => $existing['share_percent'] ?? null,
                'status' => $existing['status'] ?? null,
                'membership_id' => $existing['membership_id'] ?? null,
            ],
            [
                'full_name' => $data['full_name'],
                'relationship' => $data['relationship'],
                'share_percent' => (float) $data['share_percent'],
                'status' => $status,
                'membership_id' => (int) $data['membership_id'],
                'is_primary' => $isPrimary,
            ],
            ['user_id' => $this->userId()],
            $sid,
            $this->userId()
        );

        Session::flash('success', 'Beneficiary updated.');
        return $this->redirect('/app/beneficiaries/' . $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('beneficiaries.manage');
        $sid = $this->requireStokvel();
        $existing = $this->loadBeneficiary((int) $id, $sid);
        if ($existing === null) {
            Session::flash('error', 'Beneficiary not found.');
            return $this->redirect('/app/beneficiaries');
        }

        $status = (string) ($existing['status'] ?? 'active');
        // Prefer soft deactivate for active records; hard-delete only when inactive / unused.
        if ($status === 'active') {
            $this->db()->exec(
                'UPDATE beneficiaries SET status = :status, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
                ['status' => 'inactive', 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
            );
            $this->audit()->log(
                'beneficiary.deactivated',
                'beneficiary',
                (int) $id,
                ['status' => 'active'],
                ['status' => 'inactive', 'reason' => 'destroy_preferred_soft'],
                ['user_id' => $this->userId()],
                $sid,
                $this->userId()
            );
            Session::flash('success', 'Beneficiary is active — deactivated instead of permanently deleted.');
            return $this->redirect('/app/beneficiaries/' . $id);
        }

        $this->db()->exec(
            'DELETE FROM beneficiaries WHERE id = :id AND stokvel_id = :sid',
            ['id' => (int) $id, 'sid' => $sid]
        );
        $this->audit()->log(
            'beneficiary.deleted',
            'beneficiary',
            (int) $id,
            [
                'full_name' => $existing['full_name'] ?? null,
                'membership_id' => $existing['membership_id'] ?? null,
                'status' => $status,
            ],
            null,
            ['user_id' => $this->userId()],
            $sid,
            $this->userId()
        );
        Session::flash('success', 'Beneficiary permanently deleted.');
        return $this->redirect('/app/beneficiaries');
    }

    public function deactivate(Request $request, string $id): Response
    {
        return $this->setStatus($id, 'inactive');
    }

    public function reactivate(Request $request, string $id): Response
    {
        return $this->setStatus($id, 'active');
    }

    private function setStatus(string $id, string $status): Response
    {
        $this->requirePermission('beneficiaries.manage');
        $sid = $this->requireStokvel();
        $existing = $this->loadBeneficiary((int) $id, $sid);
        if ($existing === null) {
            Session::flash('error', 'Beneficiary not found.');
            return $this->redirect('/app/beneficiaries');
        }

        $this->db()->exec(
            'UPDATE beneficiaries SET status = :status, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['status' => $status, 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );

        $action = $status === 'active' ? 'beneficiary.reactivated' : 'beneficiary.deactivated';
        $this->audit()->log(
            $action,
            'beneficiary',
            (int) $id,
            ['status' => $existing['status'] ?? null],
            ['status' => $status],
            ['user_id' => $this->userId()],
            $sid,
            $this->userId()
        );

        Session::flash('success', $status === 'active' ? 'Beneficiary reactivated.' : 'Beneficiary deactivated.');
        return $this->redirect('/app/beneficiaries');
    }

    /** @return array<string, mixed> */
    private function collectInput(Request $request): array
    {
        return [
            'membership_id' => (int) $request->input('membership_id', 0),
            'full_name' => trim((string) $request->input('full_name', '')),
            'relationship' => trim((string) $request->input('relationship', '')),
            'share_percent' => (float) $request->input('share_percent', 100),
            'phone' => trim((string) $request->input('phone', '')),
            'email' => trim((string) $request->input('email', '')),
            'id_number' => preg_replace('/\s+/', '', trim((string) $request->input('id_number', ''))) ?? '',
            'is_primary' => $request->input('is_primary') ? 1 : 0,
            'status' => trim((string) $request->input('status', 'active')),
        ];
    }

    private function assertShareTotal(int $stokvelId, int $membershipId, float $newShare, ?int $excludeId): void
    {
        if ($newShare < 0 || $newShare > 100) {
            throw new \RuntimeException('Beneficiary share must be between 0 and 100%.');
        }

        $rows = $this->db()->fetchAll(
            'SELECT id, share_percent FROM beneficiaries
             WHERE stokvel_id = :sid AND membership_id = :mid AND status = :status',
            ['sid' => $stokvelId, 'mid' => $membershipId, 'status' => 'active']
        );

        $total = $newShare;
        foreach ($rows as $row) {
            if ($excludeId !== null && (int) $row['id'] === $excludeId) {
                continue;
            }
            $total += (float) $row['share_percent'];
        }

        if (round($total, 2) > 100.01) {
            throw new \RuntimeException(
                'Beneficiary shares would total ' . number_format($total, 2) . '% — keep the combined active share at or under 100%.'
            );
        }
    }

    private function clearPrimary(int $membershipId, int $stokvelId, ?int $exceptId = null): void
    {
        if ($exceptId !== null) {
            $this->db()->exec(
                'UPDATE beneficiaries SET is_primary = 0, updated_at = :ua
                 WHERE stokvel_id = :sid AND membership_id = :mid AND id != :id',
                ['ua' => now_utc(), 'sid' => $stokvelId, 'mid' => $membershipId, 'id' => $exceptId]
            );
            return;
        }
        $this->db()->exec(
            'UPDATE beneficiaries SET is_primary = 0, updated_at = :ua
             WHERE stokvel_id = :sid AND membership_id = :mid',
            ['ua' => now_utc(), 'sid' => $stokvelId, 'mid' => $membershipId]
        );
    }

    private function maskedIdNumber(string $plain): ?string
    {
        $plain = trim($plain);
        if ($plain === '') {
            return null;
        }
        // Store masked form only (VARCHAR(13)); never persist a raw SA ID.
        return SensitiveData::maskId($plain);
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);
        return $value !== '' ? $value : null;
    }

    /** @return array<string, mixed>|null */
    private function loadBeneficiary(int $id, int $stokvelId): ?array
    {
        return $this->db()->fetch(
            'SELECT b.*, p.first_name, p.last_name, sm.member_number, u.email AS member_email
             FROM beneficiaries b
             INNER JOIN memberships sm ON sm.id = b.membership_id AND sm.stokvel_id = b.stokvel_id
             INNER JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE b.id = :id AND b.stokvel_id = :sid
             LIMIT 1',
            ['id' => $id, 'sid' => $stokvelId]
        );
    }

    /** @return array<string, mixed>|null */
    private function loadMembership(int $membershipId, int $stokvelId): ?array
    {
        return $this->db()->fetch(
            'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $membershipId, 'sid' => $stokvelId]
        );
    }

    /** @return list<array<string, mixed>> */
    private function loadMembers(int $stokvelId): array
    {
        return $this->db()->fetchAll(
            "SELECT sm.id, p.first_name, p.last_name, sm.member_number
             FROM memberships sm
             JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE sm.stokvel_id = :sid AND sm.status = 'active'
             ORDER BY p.last_name, p.first_name",
            ['sid' => $stokvelId]
        );
    }

    private function audit(): AuditService
    {
        return app()->container()->get(AuditService::class);
    }
}
