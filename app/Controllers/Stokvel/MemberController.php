<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\Membership\MemberChangeRequestService;
use App\Services\PermissionService;
use App\Support\SensitiveData;

final class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('members.view');
        $sid = $this->requireStokvel();
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $status = (string) $request->query('status', '');

        $params = ['sid' => $sid];
        $where = 'sm.stokvel_id = :sid';
        if ($status !== '') {
            $where .= ' AND sm.status = :status';
            $params['status'] = $status;
        }

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM memberships sm WHERE {$where}",
            "SELECT sm.*, p.first_name, p.last_name, u.email, u.mobile FROM memberships sm JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE {$where} ORDER BY sm.created_at DESC",
            $params,
            $page,
            $perPage,
            url('/app/members'),
            array_filter(['status' => $status])
        );

        return $this->render('stokvel/members/index', [
            'title' => 'Members',
            'activeNav' => 'members',
            'navContext' => 'app',
            'paginator' => $paginator,
            'statusFilter' => $status,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('members.view');
        $sid = $this->requireStokvel();
        $member = $this->db()->fetch(
            'SELECT sm.*, p.first_name, p.last_name, u.email, u.mobile FROM memberships sm JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE sm.id = :id AND sm.stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$member) {
            return $this->redirect('/app/members');
        }

        $userId = (int) $member['user_id'];
        $profile = $this->db()->fetch(
            'SELECT id_number_encrypted FROM user_profiles WHERE user_id = :uid LIMIT 1',
            ['uid' => $userId]
        );
        $idPlain = SensitiveData::decrypt($profile['id_number_encrypted'] ?? null);

        $beneficiaries = $this->db()->fetchAll(
            'SELECT * FROM beneficiaries WHERE stokvel_id = :sid AND membership_id = :mid ORDER BY is_primary DESC, full_name ASC',
            ['sid' => $sid, 'mid' => (int) $member['id']]
        );

        $pendingChanges = $this->db()->fetchAll(
            'SELECT id, change_type, created_at FROM member_change_requests
             WHERE stokvel_id = :sid AND membership_id = :mid AND status = :status
             ORDER BY created_at DESC',
            ['sid' => $sid, 'mid' => (int) $member['id'], 'status' => 'pending_member']
        );

        $canManage = $this->tenant()->can('members.manage');
        $permissionOptions = [];
        $grantedPermissionCodes = [];
        if ($canManage) {
            $permissionOptions = $this->permissionOptionsForMember();
            $grantedPermissionCodes = $this->grantedPermissionCodes((int) $member['id'], $sid);
        }

        $stokvelMin = $this->db()->fetch(
            'SELECT minimum_contribution FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $sid]
        );

        return $this->render('stokvel/members/show', [
            'title' => trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')),
            'activeNav' => 'members',
            'navContext' => 'app',
            'member' => $member,
            'idMasked' => SensitiveData::maskId($idPlain),
            'beneficiaries' => $beneficiaries,
            'pendingChanges' => $pendingChanges,
            'canManage' => $canManage,
            'permissionOptions' => $permissionOptions,
            'grantedPermissionCodes' => $grantedPermissionCodes,
            'defaultMinimumContribution' => $stokvelMin['minimum_contribution'] ?? null,
        ]);
    }

    public function updateMinimumContribution(Request $request, string $id): Response
    {
        $this->requirePermission('members.manage');
        $sid = $this->requireStokvel();
        $member = $this->db()->fetch(
            'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$member) {
            return $this->redirect('/app/members');
        }

        $raw = trim((string) $request->input('minimum_contribution', ''));
        $value = null;
        if ($raw !== '') {
            if (!is_numeric($raw) || (float) $raw < 0) {
                Session::flash('error', 'Minimum contribution must be a non-negative amount.');
                return $this->redirect('/app/members/' . $id);
            }
            $value = number_format((float) $raw, 2, '.', '');
        }

        try {
            $this->db()->exec(
                'UPDATE memberships SET minimum_contribution = :min, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
                ['min' => $value, 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
            );
            Session::flash('success', $value === null
                ? 'Member will use the stokvel default minimum contribution.'
                : 'Member minimum contribution updated.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not save minimum contribution. Run migrations if the column is missing.');
        }

        return $this->redirect('/app/members/' . $id);
    }

    public function editForm(Request $request, string $id): Response
    {
        $this->requirePermission('members.manage');
        $sid = $this->requireStokvel();
        $member = $this->loadMemberWithProfile((int) $id, $sid);
        if (!$member) {
            return $this->redirect('/app/members');
        }

        $idPlain = SensitiveData::decrypt($member['id_number_encrypted'] ?? null);

        return $this->render('stokvel/members/edit', [
            'title' => 'Edit member',
            'activeNav' => 'members',
            'navContext' => 'app',
            'member' => $member,
            'idMasked' => SensitiveData::maskId($idPlain),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('members.manage');
        $sid = $this->requireStokvel();
        $member = $this->loadMemberWithProfile((int) $id, $sid);
        if (!$member) {
            return $this->redirect('/app/members');
        }

        $data = [
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'mobile' => trim((string) $request->input('mobile', '')),
            'id_number' => trim((string) $request->input('id_number', '')),
        ];
        $validator = Validator::make($data, [
            'first_name' => 'required|min:2',
            'last_name' => 'required|min:2',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Could not submit profile change.');
            return $this->redirect('/app/members/' . $id . '/edit');
        }

        if ($data['id_number'] !== '' && str_contains($data['id_number'], '•')) {
            $data['id_number'] = '';
        }

        try {
            $service = app()->container()->get(MemberChangeRequestService::class);
            $service->submit(
                $sid,
                (int) $member['id'],
                (int) $this->userId(),
                'profile_update',
                $data
            );
            Session::flash('success', 'Profile change submitted for member approval.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not submit profile change.');
            return $this->redirect('/app/members/' . $id . '/edit');
        }

        return $this->redirect('/app/members/' . $id);
    }

    public function storeBeneficiary(Request $request, string $id): Response
    {
        $this->requirePermission('members.manage');
        return $this->submitBeneficiaryChange($request, (int) $id, 'beneficiary_create', null);
    }

    public function updateBeneficiary(Request $request, string $id, string $beneficiaryId): Response
    {
        $this->requirePermission('members.manage');
        return $this->submitBeneficiaryChange($request, (int) $id, 'beneficiary_update', (int) $beneficiaryId);
    }

    public function deleteBeneficiary(Request $request, string $id, string $beneficiaryId): Response
    {
        $this->requirePermission('members.manage');
        $sid = $this->requireStokvel();
        $member = $this->loadMemberWithProfile((int) $id, $sid);
        if (!$member) {
            return $this->redirect('/app/members');
        }

        $existing = $this->db()->fetch(
            'SELECT * FROM beneficiaries WHERE id = :bid AND membership_id = :mid AND stokvel_id = :sid LIMIT 1',
            ['bid' => (int) $beneficiaryId, 'mid' => (int) $member['id'], 'sid' => $sid]
        );
        if (!$existing) {
            Session::flash('error', 'Beneficiary not found.');
            return $this->redirect('/app/members/' . $id);
        }

        try {
            $service = app()->container()->get(MemberChangeRequestService::class);
            $service->submit(
                $sid,
                (int) $member['id'],
                (int) $this->userId(),
                'beneficiary_delete',
                [
                    'beneficiary_id' => (int) $beneficiaryId,
                    'full_name' => $existing['full_name'] ?? '',
                ]
            );
            Session::flash('success', 'Beneficiary removal submitted for member approval.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not submit change.');
        }

        return $this->redirect('/app/members/' . $id);
    }

    public function updatePermissions(Request $request, string $id): Response
    {
        $this->requirePermission('members.manage');
        $sid = $this->requireStokvel();
        $member = $this->loadMemberWithProfile((int) $id, $sid);
        if (!$member) {
            return $this->redirect('/app/members');
        }

        $selected = $request->input('permissions', []);
        if (!is_array($selected)) {
            $selected = [];
        }
        $selected = array_values(array_unique(array_map('strval', $selected)));
        $allowed = PermissionService::APP_PERMISSIONS;
        $selected = array_values(array_intersect($selected, $allowed));

        $now = now_utc();
        $grantedBy = (int) $this->userId();

        foreach ($allowed as $code) {
            $permissionId = $this->ensurePermissionId($code);
            if ($permissionId === null) {
                continue;
            }
            $shouldGrant = in_array($code, $selected, true);
            $existing = $this->db()->fetch(
                'SELECT id, granted FROM membership_permissions
                 WHERE membership_id = :mid AND permission_id = :pid LIMIT 1',
                ['mid' => (int) $member['id'], 'pid' => $permissionId]
            );
            if ($existing) {
                $this->db()->exec(
                    'UPDATE membership_permissions SET granted = :granted, granted_by_user_id = :by, updated_at = :ua WHERE id = :id',
                    ['granted' => $shouldGrant ? 1 : 0, 'by' => $grantedBy, 'ua' => $now, 'id' => (int) $existing['id']]
                );
            } elseif ($shouldGrant) {
                $this->db()->insert(
                    'INSERT INTO membership_permissions
                        (stokvel_id, membership_id, permission_id, granted, granted_by_user_id, created_at, updated_at)
                     VALUES (:sid, :mid, :pid, 1, :by, :ca, :ua)',
                    [
                        'sid' => $sid,
                        'mid' => (int) $member['id'],
                        'pid' => $permissionId,
                        'by' => $grantedBy,
                        'ca' => $now,
                        'ua' => $now,
                    ]
                );
            }
        }

        Session::flash('success', 'Permission overrides updated.');
        return $this->redirect('/app/members/' . $id);
    }

    public function inviteForm(Request $request): Response
    {
        $this->requirePermission('members.invite');
        return $this->render('stokvel/members/invite', [
            'title' => 'Invite member',
            'activeNav' => 'members',
            'navContext' => 'app',
        ]);
    }

    public function invite(Request $request): Response
    {
        $this->requirePermission('members.invite');
        $this->requireStokvel();
        $data = [
            'email' => trim((string) $request->input('email', '')),
            'mobile' => trim((string) $request->input('mobile', '')),
            'role' => (string) $request->input('role', 'ordinary'),
        ];
        $roleMap = [
            'ordinary' => 'ordinary_member',
            'committee' => 'committee_member',
        ];
        $roleCode = $roleMap[$data['role']] ?? $data['role'];
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'role' => 'required|in:chairperson,treasurer,secretary,committee,ordinary,auditor,ordinary_member,committee_member',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            $this->flashErrors($validator->errors());
            $errors = $validator->errors();
            $first = 'Could not send invitation.';
            foreach ($errors as $msgs) {
                if (is_array($msgs) && $msgs !== []) {
                    $first = (string) $msgs[0];
                    break;
                }
            }
            Session::flash('error', $first);
            return $this->redirect('/app/members/invite');
        }

        $role = $this->db()->fetch(
            'SELECT id FROM roles WHERE code = :code AND stokvel_id IS NULL LIMIT 1',
            ['code' => $roleCode]
        );

        try {
            /** @var \App\Services\Membership\MembershipService $memberships */
            $memberships = app()->container()->get(\App\Services\Membership\MembershipService::class);
            $memberships->invite(
                $data['email'],
                $data['mobile'] !== '' ? $data['mobile'] : null,
                $role ? (int) $role['id'] : null,
                (int) $this->userId()
            );
        } catch (\Throwable $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not send invitation.');
            return $this->redirect('/app/members/invite');
        }

        Session::flash('success', 'Invitation sent to ' . $data['email'] . '.');
        return $this->redirect('/app/members');
    }

    public function suspend(Request $request, string $id): Response
    {
        $this->requirePermission('members.manage');
        $this->updateMemberStatus((int) $id, 'suspended');
        Session::flash('success', 'Member suspended.');
        return $this->redirectBack($request, '/app/members/' . $id);
    }

    public function reinstate(Request $request, string $id): Response
    {
        $this->requirePermission('members.manage');
        $this->updateMemberStatus((int) $id, 'active');
        Session::flash('success', 'Member reinstated.');
        return $this->redirectBack($request, '/app/members/' . $id);
    }

    public function resign(Request $request, string $id): Response
    {
        $this->requirePermission('members.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE memberships SET status = :st, resigned_at = :ra, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['st' => 'resigned', 'ra' => now_utc(), 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Member marked as resigned.');
        return $this->redirect('/app/members');
    }

    public function statement(Request $request, string $id): Response
    {
        $this->requirePermission('members.view');
        $sid = $this->requireStokvel();
        $member = $this->db()->fetch(
            'SELECT sm.*, p.first_name, p.last_name FROM memberships sm JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE sm.id = :id AND sm.stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$member) {
            return $this->redirect('/app/members');
        }

        $lines = $this->memberStatementLines($sid, (int) $member['id']);

        $layout = $request->query('print') ? 'layouts/print' : 'layouts/app';
        return $this->render('stokvel/members/statement', [
            'title' => 'Member statement',
            'activeNav' => 'members',
            'navContext' => 'app',
            'member' => $member,
            'lines' => $lines,
        ], $layout);
    }

    private function updateMemberStatus(int $memberId, string $status): void
    {
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE memberships SET status = :st, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['st' => $status, 'ua' => now_utc(), 'id' => $memberId, 'sid' => $sid]
        );
    }

    /** @return list<array<string, mixed>> */
    private function memberStatementLines(int $stokvelId, int $membershipId): array
    {
        $charges = $this->db()->fetchAll(
            "SELECT due_date AS entry_date, description, amount, 'charge' AS entry_type, charge_code AS reference
             FROM member_charges
             WHERE stokvel_id = :sid AND membership_id = :mid
             ORDER BY due_date DESC, id DESC
             LIMIT 100",
            ['sid' => $stokvelId, 'mid' => $membershipId]
        );
        $payments = $this->db()->fetchAll(
            "SELECT paid_at AS entry_date, CONCAT('Payment ', COALESCE(payment_method, '')) AS description,
                    amount, 'payment' AS entry_type, COALESCE(transaction_ref, CAST(id AS CHAR)) AS reference
             FROM payment_transactions
             WHERE stokvel_id = :sid AND membership_id = :mid
               AND status IN ('posted','completed','allocated','confirmed')
             ORDER BY paid_at DESC, id DESC
             LIMIT 100",
            ['sid' => $stokvelId, 'mid' => $membershipId]
        );

        $lines = array_merge($charges, $payments);
        usort($lines, static function (array $a, array $b): int {
            return strcmp((string) ($b['entry_date'] ?? ''), (string) ($a['entry_date'] ?? ''));
        });

        return array_slice($lines, 0, 200);
    }

    /** @return array<string, mixed>|null */
    private function loadMemberWithProfile(int $memberId, int $stokvelId): ?array
    {
        return $this->db()->fetch(
            'SELECT sm.*, p.first_name, p.last_name, p.id_number_encrypted, u.email, u.mobile
             FROM memberships sm
             JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE sm.id = :id AND sm.stokvel_id = :sid LIMIT 1',
            ['id' => $memberId, 'sid' => $stokvelId]
        );
    }

    private function submitBeneficiaryChange(Request $request, int $memberId, string $changeType, ?int $beneficiaryId): Response
    {
        $sid = $this->requireStokvel();
        $member = $this->loadMemberWithProfile($memberId, $sid);
        if (!$member) {
            return $this->redirect('/app/members');
        }

        $payload = [
            'full_name' => trim((string) $request->input('full_name', '')),
            'id_number' => trim((string) $request->input('id_number', '')),
            'relationship' => trim((string) $request->input('relationship', '')),
            'share_percent' => (float) $request->input('share_percent', 100),
            'phone' => trim((string) $request->input('phone', '')),
            'email' => trim((string) $request->input('email', '')),
            'is_primary' => $request->input('is_primary') ? 1 : 0,
        ];
        if ($beneficiaryId !== null) {
            $payload['beneficiary_id'] = $beneficiaryId;
        }

        $validator = Validator::make($payload, [
            'full_name' => 'required|min:2',
            'relationship' => 'required|min:2',
        ]);
        if ($validator->fails()) {
            Session::flash('error', 'Please complete all required beneficiary fields.');
            return $this->redirect('/app/members/' . $memberId);
        }

        try {
            $service = app()->container()->get(MemberChangeRequestService::class);
            $service->submit($sid, (int) $member['id'], (int) $this->userId(), $changeType, $payload);
            Session::flash('success', 'Beneficiary change submitted for member approval.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not submit change.');
        }

        return $this->redirect('/app/members/' . $memberId);
    }

    /** @return list<array{code: string, label: string}> */
    private function permissionOptionsForMember(): array
    {
        $options = [];
        foreach (PermissionService::APP_PERMISSIONS as $code) {
            $options[] = [
                'code' => $code,
                'label' => ucwords(str_replace(['.', '_'], [' — ', ' '], $code)),
            ];
        }
        return $options;
    }

    /** @return list<string> */
    private function grantedPermissionCodes(int $membershipId, int $stokvelId): array
    {
        $rows = $this->db()->fetchAll(
            'SELECT p.code FROM membership_permissions mp
             INNER JOIN permissions p ON p.id = mp.permission_id
             WHERE mp.membership_id = :mid AND mp.stokvel_id = :sid AND mp.granted = 1',
            ['mid' => $membershipId, 'sid' => $stokvelId]
        );
        $codes = [];
        foreach ($rows as $row) {
            $codes[] = (string) $row['code'];
        }
        return $codes;
    }

    private function ensurePermissionId(string $code): ?int
    {
        $row = $this->db()->fetch('SELECT id FROM permissions WHERE code = :code LIMIT 1', ['code' => $code]);
        if ($row) {
            return (int) $row['id'];
        }

        $now = now_utc();
        $label = ucwords(str_replace(['.', '_'], ' ', $code));
        try {
            $id = (int) $this->db()->insert(
                'INSERT INTO permissions (code, name, description, created_at, updated_at)
                 VALUES (:code, :name, :description, :ca, :ua)',
                [
                    'code' => $code,
                    'name' => $label,
                    'description' => 'App permission ' . $code,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
            return $id > 0 ? $id : null;
        } catch (\Throwable) {
            $row = $this->db()->fetch('SELECT id FROM permissions WHERE code = :code LIMIT 1', ['code' => $code]);
            return $row ? (int) $row['id'] : null;
        }
    }
}
