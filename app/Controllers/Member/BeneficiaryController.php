<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Support\SensitiveData;

/**
 * Members manage their own beneficiaries. Changes apply immediately (auto-approved).
 * Shares of active beneficiaries must always total exactly 100%.
 */
final class BeneficiaryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $ctx = $this->requireMemberContext();
        $beneficiaries = $this->db()->fetchAll(
            'SELECT * FROM beneficiaries
             WHERE stokvel_id = :sid AND membership_id = :mid
             ORDER BY is_primary DESC, full_name ASC',
            ['sid' => $ctx['stokvel_id'], 'mid' => $ctx['membership_id']]
        );
        $total = 0.0;
        foreach ($beneficiaries as $b) {
            if (($b['status'] ?? 'active') === 'active') {
                $total += (float) ($b['share_percent'] ?? 0);
            }
        }

        return $this->render('member/beneficiaries/index', [
            'title' => 'My beneficiaries',
            'activeNav' => 'member-beneficiaries',
            'navContext' => 'member',
            'beneficiaries' => $beneficiaries,
            'shareTotal' => round($total, 2),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requireAuth();
        $ctx = $this->requireMemberContext();
        $data = $this->collect($request);
        $validator = Validator::make($data, [
            'full_name' => 'required|min:2',
            'relationship' => 'required|min:2',
            'share_percent' => 'required|numeric|min:0|max:100',
        ]);
        if ($validator->fails()) {
            Session::flash('error', 'Please complete beneficiary details.');
            return $this->redirect('/member/beneficiaries');
        }

        try {
            $this->assertExactHundred($ctx['stokvel_id'], $ctx['membership_id'], (float) $data['share_percent'], null);
            $now = now_utc();
            if (!empty($data['is_primary'])) {
                $this->db()->exec(
                    'UPDATE beneficiaries SET is_primary = 0, updated_at = :ua
                     WHERE stokvel_id = :sid AND membership_id = :mid',
                    ['ua' => $now, 'sid' => $ctx['stokvel_id'], 'mid' => $ctx['membership_id']]
                );
            }
            try {
                $this->db()->insert(
                    'INSERT INTO beneficiaries
                        (stokvel_id, membership_id, full_name, id_number, relationship, share_percent,
                         phone, email, is_primary, status, approval_status, source, created_at, updated_at)
                     VALUES
                        (:sid, :mid, :name, :idn, :rel, :share, :phone, :email, :primary, :status, :appr, :src, :ca, :ua)',
                    [
                        'sid' => $ctx['stokvel_id'],
                        'mid' => $ctx['membership_id'],
                        'name' => $data['full_name'],
                        'idn' => $this->maskedId((string) $data['id_number']),
                        'rel' => $data['relationship'],
                        'share' => (float) $data['share_percent'],
                        'phone' => $data['phone'] !== '' ? $data['phone'] : null,
                        'email' => $data['email'] !== '' ? $data['email'] : null,
                        'primary' => !empty($data['is_primary']) ? 1 : 0,
                        'status' => 'active',
                        'appr' => 'approved',
                        'src' => 'member',
                        'ca' => $now,
                        'ua' => $now,
                    ]
                );
            } catch (\Throwable) {
                $this->db()->insert(
                    'INSERT INTO beneficiaries
                        (stokvel_id, membership_id, full_name, id_number, relationship, share_percent,
                         phone, email, is_primary, status, created_at, updated_at)
                     VALUES
                        (:sid, :mid, :name, :idn, :rel, :share, :phone, :email, :primary, :status, :ca, :ua)',
                    [
                        'sid' => $ctx['stokvel_id'],
                        'mid' => $ctx['membership_id'],
                        'name' => $data['full_name'],
                        'idn' => $this->maskedId((string) $data['id_number']),
                        'rel' => $data['relationship'],
                        'share' => (float) $data['share_percent'],
                        'phone' => $data['phone'] !== '' ? $data['phone'] : null,
                        'email' => $data['email'] !== '' ? $data['email'] : null,
                        'primary' => !empty($data['is_primary']) ? 1 : 0,
                        'status' => 'active',
                        'ca' => $now,
                        'ua' => $now,
                    ]
                );
            }
            Session::flash('success', 'Beneficiary added.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not add beneficiary.');
        }

        return $this->redirect('/member/beneficiaries');
    }

    public function update(Request $request, string $id): Response
    {
        $this->requireAuth();
        $ctx = $this->requireMemberContext();
        $bid = (int) $id;
        $existing = $this->loadOwn($bid, $ctx);
        if (!$existing) {
            Session::flash('error', 'Beneficiary not found.');
            return $this->redirect('/member/beneficiaries');
        }

        $data = $this->collect($request);
        $validator = Validator::make($data, [
            'full_name' => 'required|min:2',
            'relationship' => 'required|min:2',
            'share_percent' => 'required|numeric|min:0|max:100',
        ]);
        if ($validator->fails()) {
            Session::flash('error', 'Please complete beneficiary details.');
            return $this->redirect('/member/beneficiaries');
        }

        try {
            $this->assertExactHundred($ctx['stokvel_id'], $ctx['membership_id'], (float) $data['share_percent'], $bid);
            $now = now_utc();
            if (!empty($data['is_primary'])) {
                $this->db()->exec(
                    'UPDATE beneficiaries SET is_primary = 0, updated_at = :ua
                     WHERE stokvel_id = :sid AND membership_id = :mid AND id != :id',
                    ['ua' => $now, 'sid' => $ctx['stokvel_id'], 'mid' => $ctx['membership_id'], 'id' => $bid]
                );
            }
            $this->db()->exec(
                'UPDATE beneficiaries SET
                    full_name = :name, relationship = :rel, share_percent = :share,
                    phone = :phone, email = :email, is_primary = :primary, updated_at = :ua
                 WHERE id = :id AND membership_id = :mid AND stokvel_id = :sid',
                [
                    'name' => $data['full_name'],
                    'rel' => $data['relationship'],
                    'share' => (float) $data['share_percent'],
                    'phone' => $data['phone'] !== '' ? $data['phone'] : null,
                    'email' => $data['email'] !== '' ? $data['email'] : null,
                    'primary' => !empty($data['is_primary']) ? 1 : 0,
                    'ua' => $now,
                    'id' => $bid,
                    'mid' => $ctx['membership_id'],
                    'sid' => $ctx['stokvel_id'],
                ]
            );
            Session::flash('success', 'Beneficiary updated.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not update beneficiary.');
        }

        return $this->redirect('/member/beneficiaries');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requireAuth();
        $ctx = $this->requireMemberContext();
        $bid = (int) $id;
        $existing = $this->loadOwn($bid, $ctx);
        if (!$existing) {
            Session::flash('error', 'Beneficiary not found.');
            return $this->redirect('/member/beneficiaries');
        }

        $others = $this->db()->fetchAll(
            "SELECT id, share_percent FROM beneficiaries
             WHERE stokvel_id = :sid AND membership_id = :mid AND status = 'active' AND id != :id",
            ['sid' => $ctx['stokvel_id'], 'mid' => $ctx['membership_id'], 'id' => $bid]
        );
        $remaining = 0.0;
        foreach ($others as $row) {
            $remaining += (float) $row['share_percent'];
        }
        if ($others !== [] && round($remaining, 2) !== 100.0) {
            Session::flash('error', 'After removal, remaining active shares must total 100%. Update other shares first, then remove.');
            return $this->redirect('/member/beneficiaries');
        }

        $this->db()->exec(
            'DELETE FROM beneficiaries WHERE id = :id AND membership_id = :mid AND stokvel_id = :sid',
            ['id' => $bid, 'mid' => $ctx['membership_id'], 'sid' => $ctx['stokvel_id']]
        );
        Session::flash('success', 'Beneficiary removed.');
        return $this->redirect('/member/beneficiaries');
    }

    /** @return array{stokvel_id:int,membership_id:int} */
    private function requireMemberContext(): array
    {
        $membership = $this->tenant()->membership();
        $stokvelId = $this->tenant()->stokvelId();
        if ($membership === null || $stokvelId === null) {
            throw new \App\Exceptions\HttpException(403, 'No active membership.');
        }
        return [
            'stokvel_id' => (int) $stokvelId,
            'membership_id' => (int) $membership['id'],
        ];
    }

    /** @param array{stokvel_id:int,membership_id:int} $ctx */
    private function loadOwn(int $id, array $ctx): ?array
    {
        return $this->db()->fetch(
            'SELECT * FROM beneficiaries WHERE id = :id AND stokvel_id = :sid AND membership_id = :mid LIMIT 1',
            ['id' => $id, 'sid' => $ctx['stokvel_id'], 'mid' => $ctx['membership_id']]
        );
    }

    /** @return array<string, mixed> */
    private function collect(Request $request): array
    {
        return [
            'full_name' => trim((string) $request->input('full_name', '')),
            'relationship' => trim((string) $request->input('relationship', '')),
            'share_percent' => (float) $request->input('share_percent', 100),
            'phone' => trim((string) $request->input('phone', '')),
            'email' => trim((string) $request->input('email', '')),
            'id_number' => preg_replace('/\s+/', '', trim((string) $request->input('id_number', ''))) ?? '',
            'is_primary' => $request->input('is_primary') ? 1 : 0,
        ];
    }

    private function assertExactHundred(int $stokvelId, int $membershipId, float $newShare, ?int $excludeId): void
    {
        if ($newShare < 0 || $newShare > 100) {
            throw new \RuntimeException('Share must be between 0 and 100%.');
        }
        $rows = $this->db()->fetchAll(
            "SELECT id, share_percent FROM beneficiaries
             WHERE stokvel_id = :sid AND membership_id = :mid AND status = 'active'",
            ['sid' => $stokvelId, 'mid' => $membershipId]
        );
        $total = $newShare;
        foreach ($rows as $row) {
            if ($excludeId !== null && (int) $row['id'] === $excludeId) {
                continue;
            }
            $total += (float) $row['share_percent'];
        }
        if (round($total, 2) !== 100.0) {
            throw new \RuntimeException(
                'Active beneficiary shares must total exactly 100% (would be ' . number_format($total, 2) . '%).'
            );
        }
    }

    private function maskedId(string $plain): ?string
    {
        $plain = trim($plain);
        if ($plain === '') {
            return null;
        }
        return SensitiveData::maskId($plain);
    }
}
