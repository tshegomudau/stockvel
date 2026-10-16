<?php

declare(strict_types=1);

namespace Database\Seeders;

use PDO;
use RuntimeException;

/**
 * Deterministic accounts for end-to-end testing.
 *
 * Password for all seeded users: Password123!
 *
 * Platform:
 *   admin@stockvel.local       — Super Admin
 *   support@stockvel.local     — Platform Support
 *   auditor@stockvel.local     — Platform Auditor
 *
 * Stokvel A (Ubuntu Savings Club) + Stokvel B (Masakhane Burial Society)
 * plus matrix accounts documented in docs/END_TO_END_TEST_PLAN.md.
 */
final class E2ETestSeeder
{
    private PDO $pdo;
    private string $now = '';
    private string $passwordHash = '';

    /** @var array<string, int> */
    private array $roleIds = [];

    /** @var array<string, int> */
    private array $permissionIds = [];

    /** @var array<string, int> */
    private array $stokvelIds = [];

    public function run(PDO $pdo): void
    {
        $this->pdo = $pdo;
        $this->now = gmdate('Y-m-d H:i:s');
        $this->passwordHash = password_hash(
            'Password123!',
            defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT
        );

        require_once __DIR__ . '/DemoSeeder.php';
        (new DemoSeeder())->run($pdo);

        $this->loadRoleIds();
        $this->loadPermissionIds();
        $this->loadStokvelIds();
        $this->ensureFullChartOfAccounts();
        $this->seedPlatformAuditor();
        $this->seedMissingCommitteeRoles();
        $this->seedSuspendedMember();
        $this->seedDualMembershipUser();
        $this->seedUnverifiedUser();
        $this->seedLockedUser();
        $this->seedInvitations();
        $this->seedCustomPermissionUser();
        $this->seedNoReportsUser();
        $this->seedPendingApprovals();
    }

    private function loadRoleIds(): void
    {
        foreach ($this->pdo->query('SELECT id, code FROM roles WHERE stokvel_id IS NULL') as $row) {
            $this->roleIds[(string) $row['code']] = (int) $row['id'];
        }
    }

    private function loadPermissionIds(): void
    {
        foreach ($this->pdo->query('SELECT id, code FROM permissions') as $row) {
            $this->permissionIds[(string) $row['code']] = (int) $row['id'];
        }
    }

    private function loadStokvelIds(): void
    {
        foreach ($this->pdo->query('SELECT id, slug FROM stokvels') as $row) {
            if ($row['slug'] === 'ubuntu-savings') {
                $this->stokvelIds['ubuntu'] = (int) $row['id'];
            }
            if ($row['slug'] === 'masakhane-burial') {
                $this->stokvelIds['masakhane'] = (int) $row['id'];
            }
        }
        if (count($this->stokvelIds) < 2) {
            throw new RuntimeException('E2E seeder expected Ubuntu and Masakhane stokvels from DemoSeeder.');
        }
    }

    private function ensureFullChartOfAccounts(): void
    {
        $accounts = [
            ['1000', 'Cash on Hand', 'asset', 'cash'],
            ['1010', 'Bank Account', 'asset', 'bank'],
            ['1100', 'Member Receivables', 'asset', 'receivable'],
            ['1200', 'Loan Receivables', 'asset', 'loan_receivable'],
            ['2000', 'Member Credits', 'liability', 'member_credit'],
            ['2100', 'Accounts Payable', 'liability', 'payable'],
            ['2200', 'Payouts Payable', 'liability', 'payout_payable'],
            ['3000', 'Fund Balance', 'equity', 'fund_balance'],
            ['3100', 'Retained Surplus', 'equity', 'retained'],
            ['4000', 'Contribution Income', 'income', 'contributions'],
            ['4100', 'Penalty Income', 'income', 'penalties'],
            ['4200', 'Interest Income', 'income', 'interest'],
            ['4300', 'Other Income', 'income', 'other_income'],
            ['5000', 'Administrative Expenses', 'expense', 'admin'],
            ['5100', 'Meeting Expenses', 'expense', 'meeting'],
            ['5200', 'Bank Charges', 'expense', 'bank_charges'],
            ['5300', 'Loan Loss', 'expense', 'loan_loss'],
            ['5400', 'Payout Expenses', 'expense', 'payout'],
            ['5500', 'Other Expenses', 'expense', 'other_expense'],
        ];

        $find = $this->pdo->prepare(
            'SELECT id FROM chart_of_accounts WHERE stokvel_id = :sid AND code = :code LIMIT 1'
        );
        $insert = $this->pdo->prepare(
            'INSERT INTO chart_of_accounts
                (stokvel_id, code, name, account_type, sub_type, is_system, is_active, created_at, updated_at)
             VALUES
                (:stokvel_id, :code, :name, :account_type, :sub_type, 1, 1, :created_at, :updated_at)'
        );
        $update = $this->pdo->prepare(
            'UPDATE chart_of_accounts
             SET name = :name, account_type = :account_type, sub_type = :sub_type, updated_at = :updated_at
             WHERE id = :id'
        );

        foreach ($this->stokvelIds as $stokvelId) {
            foreach ($accounts as [$code, $name, $type, $subType]) {
                $find->execute(['sid' => $stokvelId, 'code' => $code]);
                $id = $find->fetchColumn();
                if ($id) {
                    $update->execute([
                        'name' => $name,
                        'account_type' => $type,
                        'sub_type' => $subType,
                        'updated_at' => $this->now,
                        'id' => (int) $id,
                    ]);
                } else {
                    $insert->execute([
                        'stokvel_id' => $stokvelId,
                        'code' => $code,
                        'name' => $name,
                        'account_type' => $type,
                        'sub_type' => $subType,
                        'created_at' => $this->now,
                        'updated_at' => $this->now,
                    ]);
                }
            }
        }
    }

    private function seedPlatformAuditor(): void
    {
        $this->createUser(
            'auditor@stockvel.local',
            '0821000003',
            'platform_auditor',
            'Fatima',
            'Hassan',
            '8701015800080',
            'active',
            $this->now
        );
    }

    private function seedMissingCommitteeRoles(): void
    {
        foreach (['ubuntu' => 'UB', 'masakhane' => 'MK'] as $key => $prefix) {
            $email = $key === 'ubuntu'
                ? 'committee.ubuntu@example.co.za'
                : 'committee.masa@example.co.za';
            $userId = $this->createUser(
                $email,
                $key === 'ubuntu' ? '0829000001' : '0729000001',
                null,
                'Committee',
                ucfirst($key),
                $key === 'ubuntu' ? '8501015800081' : '8501015800082',
                'active',
                $this->now
            );
            $this->attachMember($this->stokvelIds[$key], $userId, $prefix . '070', 'committee_member');

            $auditorEmail = $key === 'ubuntu'
                ? 'stokvel.auditor.ubuntu@example.co.za'
                : 'stokvel.auditor.masa@example.co.za';
            $auditorId = $this->createUser(
                $auditorEmail,
                $key === 'ubuntu' ? '0829000002' : '0729000002',
                null,
                'Audit',
                ucfirst($key),
                $key === 'ubuntu' ? '8601015800083' : '8601015800084',
                'active',
                $this->now
            );
            $this->attachMember($this->stokvelIds[$key], $auditorId, $prefix . '071', 'auditor');
        }
    }

    private function seedSuspendedMember(): void
    {
        $userId = $this->createUser(
            'suspended.ubuntu@example.co.za',
            '0829000010',
            null,
            'Suspended',
            'Member',
            '9101015800085',
            'active',
            $this->now
        );
        $membershipId = $this->attachMember($this->stokvelIds['ubuntu'], $userId, 'UB080', 'ordinary_member', 'suspended');
        $this->pdo->prepare(
            'UPDATE memberships SET suspended_at = :at, suspension_reason = :reason WHERE id = :id'
        )->execute([
            'at' => $this->now,
            'reason' => 'E2E suspended member fixture',
            'id' => $membershipId,
        ]);
    }

    private function seedDualMembershipUser(): void
    {
        $userId = $this->createUser(
            'dual.member@example.co.za',
            '0829000020',
            null,
            'Dual',
            'Member',
            '9201015800086',
            'active',
            $this->now
        );
        $this->attachMember($this->stokvelIds['ubuntu'], $userId, 'UB081', 'ordinary_member');
        $this->attachMember($this->stokvelIds['masakhane'], $userId, 'MK081', 'treasurer');
    }

    private function seedUnverifiedUser(): void
    {
        $this->createUser(
            'unverified@example.co.za',
            '0829000030',
            null,
            'Unverified',
            'User',
            '9301015800087',
            'active',
            null
        );
    }

    private function seedLockedUser(): void
    {
        $userId = $this->createUser(
            'locked@example.co.za',
            '0829000040',
            null,
            'Locked',
            'User',
            '9401015800088',
            'active',
            $this->now
        );
        $lockedUntil = gmdate('Y-m-d H:i:s', time() + 3600);
        $this->pdo->prepare(
            'UPDATE users SET locked_until = :until, failed_login_count = 5 WHERE id = :id'
        )->execute(['until' => $lockedUntil, 'id' => $userId]);
    }

    private function seedInvitations(): void
    {
        $chair = (int) $this->pdo->query(
            "SELECT id FROM users WHERE email = 'thabo.mokoena@example.co.za' LIMIT 1"
        )->fetchColumn();

        $this->pdo->prepare(
            'INSERT INTO invitations (stokvel_id, email, role_id, token_hash, status, invited_by_user_id, expires_at, created_at, updated_at)
             VALUES (:sid, :email, :role_id, :token_hash, :status, :by, :expires, :ca, :ua)'
        )->execute([
            'sid' => $this->stokvelIds['ubuntu'],
            'email' => 'invite.valid@example.co.za',
            'role_id' => $this->roleIds['ordinary_member'],
            'token_hash' => hash('sha256', 'valid-invite-token-e2e'),
            'status' => 'pending',
            'by' => $chair,
            'expires' => gmdate('Y-m-d H:i:s', time() + 86400 * 7),
            'ca' => $this->now,
            'ua' => $this->now,
        ]);

        $this->pdo->prepare(
            'INSERT INTO invitations (stokvel_id, email, role_id, token_hash, status, invited_by_user_id, expires_at, created_at, updated_at)
             VALUES (:sid, :email, :role_id, :token_hash, :status, :by, :expires, :ca, :ua)'
        )->execute([
            'sid' => $this->stokvelIds['ubuntu'],
            'email' => 'invite.expired@example.co.za',
            'role_id' => $this->roleIds['ordinary_member'],
            'token_hash' => hash('sha256', 'expired-invite-token-e2e'),
            'status' => 'pending',
            'by' => $chair,
            'expires' => gmdate('Y-m-d H:i:s', time() - 86400),
            'ca' => $this->now,
            'ua' => $this->now,
        ]);
    }

    private function seedCustomPermissionUser(): void
    {
        $userId = $this->createUser(
            'custom.perms@example.co.za',
            '0829000050',
            null,
            'Custom',
            'Perms',
            '9501015800089',
            'active',
            $this->now
        );
        $membershipId = $this->attachMember($this->stokvelIds['ubuntu'], $userId, 'UB082', 'ordinary_member');
        if (isset($this->permissionIds['record_contributions'])) {
            $this->pdo->prepare(
                'INSERT INTO membership_permissions (stokvel_id, membership_id, permission_id, granted, created_at, updated_at)
                 VALUES (:sid, :mid, :pid, 1, :ca, :ua)'
            )->execute([
                'sid' => $this->stokvelIds['ubuntu'],
                'mid' => $membershipId,
                'pid' => $this->permissionIds['record_contributions'],
                'ca' => $this->now,
                'ua' => $this->now,
            ]);
        }
    }

    private function seedNoReportsUser(): void
    {
        $userId = $this->createUser(
            'noreports@example.co.za',
            '0829000060',
            null,
            'No',
            'Reports',
            '9601015800090',
            'active',
            $this->now
        );
        $this->attachMember($this->stokvelIds['ubuntu'], $userId, 'UB083', 'ordinary_member');
    }

    private function createUser(
        string $email,
        string $mobile,
        ?string $platformRole,
        string $firstName,
        string $lastName,
        string $idNumber,
        string $status,
        ?string $emailVerifiedAt
    ): int {
        $existing = $this->pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existing->execute(['email' => $email]);
        $id = $existing->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, mobile, password_hash, platform_role, email_verified_at, must_change_password,
                failed_login_count, status, created_at, updated_at)
             VALUES (:email, :mobile, :password_hash, :platform_role, :email_verified_at, 0, 0, :status, :created_at, :updated_at)'
        );
        $stmt->execute([
            'email' => $email,
            'mobile' => $mobile,
            'password_hash' => $this->passwordHash,
            'platform_role' => $platformRole,
            'email_verified_at' => $emailVerifiedAt,
            'status' => $status,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $userId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare(
            'INSERT INTO user_profiles (user_id, first_name, last_name, id_number, suburb, city, province, postal_code,
                country_code, created_at, updated_at)
             VALUES (:user_id, :first_name, :last_name, :id_number, :suburb, :city, :province, :postal_code, :country_code,
                :created_at, :updated_at)'
        )->execute([
            'user_id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'id_number' => $idNumber,
            'suburb' => 'Sandton',
            'city' => 'Johannesburg',
            'province' => 'Gauteng',
            'postal_code' => '2196',
            'country_code' => 'ZA',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        return $userId;
    }

    private function seedPendingApprovals(): void
    {
        $ubuntuId = $this->stokvelIds['ubuntu'] ?? null;
        if ($ubuntuId === null) {
            return;
        }

        $requester = (int) $this->pdo->query(
            "SELECT id FROM users WHERE email = 'nomsa.dlamini@example.co.za' LIMIT 1"
        )->fetchColumn();
        if ($requester <= 0) {
            return;
        }

        $exists = $this->pdo->prepare(
            "SELECT id FROM approval_requests
             WHERE stokvel_id = :sid AND reference_type = 'expense' AND reference_id = 1 AND status = 'pending'
             LIMIT 1"
        );
        $exists->execute(['sid' => $ubuntuId]);
        if ($exists->fetchColumn()) {
            return;
        }

        $this->pdo->prepare(
            'INSERT INTO approval_requests
                (stokvel_id, request_type, reference_type, reference_id, status,
                 requested_by_user_id, requested_at, created_at, updated_at)
             VALUES
                (:sid, :rtype, :reftype, :refid, :status, :by, :ra, :ca, :ua)'
        )->execute([
            'sid' => $ubuntuId,
            'rtype' => 'expense_payment',
            'reftype' => 'expense',
            'refid' => 1,
            'status' => 'pending',
            'by' => $requester,
            'ra' => $this->now,
            'ca' => $this->now,
            'ua' => $this->now,
        ]);
    }

    private function attachMember(
        int $stokvelId,
        int $userId,
        string $memberNumber,
        string $roleCode,
        string $status = 'active'
    ): int {
        $existing = $this->pdo->prepare(
            'SELECT id FROM memberships WHERE stokvel_id = :sid AND user_id = :uid LIMIT 1'
        );
        $existing->execute(['sid' => $stokvelId, 'uid' => $userId]);
        $id = $existing->fetchColumn();
        if ($id) {
            return (int) $id;
        }

        $this->pdo->prepare(
            'INSERT INTO memberships (stokvel_id, user_id, member_number, status, joined_at, approved_at, created_at, updated_at)
             VALUES (:stokvel_id, :user_id, :member_number, :status, :joined_at, :approved_at, :created_at, :updated_at)'
        )->execute([
            'stokvel_id' => $stokvelId,
            'user_id' => $userId,
            'member_number' => $memberNumber,
            'status' => $status,
            'joined_at' => $this->now,
            'approved_at' => $this->now,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $membershipId = (int) $this->pdo->lastInsertId();

        if (!isset($this->roleIds[$roleCode])) {
            throw new RuntimeException('Unknown role code: ' . $roleCode);
        }

        $this->pdo->prepare(
            'INSERT INTO membership_roles (stokvel_id, membership_id, role_id, assigned_at, created_at)
             VALUES (:stokvel_id, :membership_id, :role_id, :assigned_at, :created_at)'
        )->execute([
            'stokvel_id' => $stokvelId,
            'membership_id' => $membershipId,
            'role_id' => $this->roleIds[$roleCode],
            'assigned_at' => $this->now,
            'created_at' => $this->now,
        ]);

        return $membershipId;
    }
}
