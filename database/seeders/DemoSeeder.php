<?php

declare(strict_types=1);

namespace Database\Seeders;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Development / demo dataset for local onboarding.
 *
 * Safe to re-run: if the platform admin already exists, the seeder skips inserts
 * and exits successfully. For a clean reload, drop the database (or use
 * `php bin/console db:reset --env=testing` on a test database) then migrate + seed.
 *
 * Password for all seeded users: Password123!
 *
 * | Email                             | Role                         |
 * |-----------------------------------|------------------------------|
 * | admin@stockvel.local              | Platform super admin         |
 * | support@stockvel.local            | Platform support             |
 * | thabo.mokoena@example.co.za       | Ubuntu chairperson           |
 * | nomsa.dlamini@example.co.za       | Ubuntu treasurer             |
 * | sipho.ndlovu@example.co.za        | Ubuntu secretary             |
 * | lerato.pillay@example.co.za       | Ubuntu ordinary member       |
 * | themba.zulu@example.co.za         | Masakhane chairperson        |
 * | patience.molefe@example.co.za     | Masakhane treasurer          |
 *
 * Tenants: Ubuntu Savings Club (`ubuntu-savings`), Masakhane Burial Society (`masakhane-burial`).
 */
final class DemoSeeder
{
    public const PLATFORM_ADMIN_EMAIL = 'admin@stockvel.local';

    private PDO $pdo;

    /** @var array<string, int> */
    private array $permissionIds = [];

    /** @var array<string, int> */
    private array $roleIds = [];

    /** @var array<string, int> */
    private array $userIds = [];

    /** @var array<string, int> */
    private array $stokvelIds = [];

    /** @var array<string, int> */
    private array $membershipIds = [];

    private string $now;

    private string $passwordHash;

    public function run(PDO $pdo): void
    {
        $this->pdo = $pdo;
        $this->now = gmdate('Y-m-d H:i:s');
        $this->passwordHash = $this->hashPassword('Password123!');

        if ($this->alreadySeeded()) {
            fwrite(STDOUT, "Demo seed already present (found " . self::PLATFORM_ADMIN_EMAIL . "); skipping.\n");
            return;
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $this->pdo->beginTransaction();
            $this->seedPermissions();
            $this->seedRoles();
            $this->seedRolePermissions();
            $this->seedPlatformUsers();
            $this->seedStokvelUsersAndTenants();
            $this->seedFinanceAndOperations();
            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Demo seed failed: ' . $e->getMessage(), 0, $e);
        } finally {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function alreadySeeded(): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => self::PLATFORM_ADMIN_EMAIL]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    private function hashPassword(string $plain): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;

        return password_hash($plain, $algo);
    }

    private function seedPermissions(): void
    {
        $permissions = [
            'manage_members' => ['Manage members', 'Invite, approve, suspend, and update member records'],
            'manage_rules' => ['Manage rules', 'Publish and version stokvel constitution and rules'],
            'record_contributions' => ['Record contributions', 'Capture member contributions and payments'],
            'approve_transactions' => ['Approve transactions', 'Maker-checker approval for financial actions'],
            'manage_loans' => ['Manage loans', 'Process loan applications, schedules, and repayments'],
            'record_expenses' => ['Record expenses', 'Capture and submit stokvel expenses'],
            'run_payouts' => ['Run payouts', 'Manage rotational payout cycles and distributions'],
            'manage_meetings' => ['Manage meetings', 'Schedule meetings, minutes, and resolutions'],
            'upload_documents' => ['Upload documents', 'Upload and organise stokvel documents'],
            'view_financial_reports' => ['View financial reports', 'Access ledgers, statements, and summaries'],
            'export_data' => ['Export data', 'Export reports and member data where permitted'],
            'view_audit' => ['View audit trail', 'Read audit logs for compliance review'],
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO permissions (code, name, description, created_at, updated_at)
             VALUES (:code, :name, :description, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), updated_at = VALUES(updated_at)'
        );
        $find = $this->pdo->prepare('SELECT id FROM permissions WHERE code = :code LIMIT 1');

        foreach ($permissions as $code => [$name, $description]) {
            $stmt->execute([
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
            $find->execute(['code' => $code]);
            $this->permissionIds[$code] = (int) $find->fetchColumn();
        }
    }

    private function seedRoles(): void
    {
        $roles = [
            'platform_super_admin' => ['Platform Super Admin', 'Full platform access across tenants'],
            'platform_support' => ['Platform Support', 'Support operations across tenants'],
            'platform_auditor' => ['Platform Auditor', 'Read-only platform audit access'],
            'chairperson' => ['Chairperson', 'Executive leadership of the stokvel'],
            'treasurer' => ['Treasurer', 'Financial stewardship and approvals'],
            'secretary' => ['Secretary', 'Administration, meetings, and records'],
            'committee_member' => ['Committee Member', 'Assists executive committee with governance'],
            'ordinary_member' => ['Ordinary Member', 'Standard participating member'],
            'auditor' => ['Auditor', 'Independent review of stokvel finances'],
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO roles (stokvel_id, code, name, description, is_system, created_at, updated_at)
             VALUES (NULL, :code, :name, :description, 1, :created_at, :updated_at)'
        );
        $find = $this->pdo->prepare(
            'SELECT id FROM roles WHERE code = :code AND stokvel_id IS NULL LIMIT 1'
        );

        foreach ($roles as $code => [$name, $description]) {
            // MySQL allows multiple NULLs in unique (stokvel_id, code), so look up first.
            $find->execute(['code' => $code]);
            $existingId = $find->fetchColumn();
            if ($existingId) {
                $this->roleIds[$code] = (int) $existingId;
                continue;
            }

            $stmt->execute([
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
            $this->roleIds[$code] = (int) $this->pdo->lastInsertId();
        }
    }

    private function seedRolePermissions(): void
    {
        $all = array_keys($this->permissionIds);

        $map = [
            'platform_super_admin' => $all,
            'platform_support' => [
                'manage_members', 'view_financial_reports', 'view_audit', 'export_data',
            ],
            'platform_auditor' => [
                'view_financial_reports', 'export_data', 'view_audit',
            ],
            'chairperson' => $all,
            'treasurer' => [
                'record_contributions', 'approve_transactions', 'manage_loans', 'record_expenses',
                'run_payouts', 'view_financial_reports', 'export_data', 'view_audit',
            ],
            'secretary' => [
                'manage_members', 'manage_rules', 'manage_meetings', 'upload_documents', 'view_audit',
            ],
            'committee_member' => [
                'approve_transactions', 'manage_meetings', 'record_expenses',
                'view_financial_reports', 'view_audit',
            ],
            'ordinary_member' => [
                // Member portal access only — no committee financial report rights.
            ],
            'auditor' => [
                'view_financial_reports', 'export_data', 'view_audit',
            ],
        ];

        $stmt = $this->pdo->prepare(
            'INSERT INTO role_permissions (role_id, permission_id, created_at)
             VALUES (:role_id, :permission_id, :created_at)'
        );

        foreach ($map as $roleCode => $permissionCodes) {
            $roleId = $this->roleIds[$roleCode];
            foreach ($permissionCodes as $permissionCode) {
                $stmt->execute([
                    'role_id' => $roleId,
                    'permission_id' => $this->permissionIds[$permissionCode],
                    'created_at' => $this->now,
                ]);
            }
        }
    }

    private function seedPlatformUsers(): void
    {
        $this->createUser(
            'admin@stockvel.local',
            '0821000001',
            'platform_super_admin',
            'Sibusiso',
            'Mahlangu',
            '8001015800084',
            'admin'
        );

        $this->createUser(
            'support@stockvel.local',
            '0821000002',
            'platform_support',
            'Ayanda',
            'Nkosi',
            '8502155800087',
            'support'
        );
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function createUser(
        string $email,
        string $mobile,
        ?string $platformRole,
        string $firstName,
        string $lastName,
        string $idNumber,
        string $key
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (email, mobile, password_hash, platform_role, email_verified_at, must_change_password,
                failed_login_count, status, created_at, updated_at)
             VALUES (:email, :mobile, :password_hash, :platform_role, :email_verified_at, 1, 0, :status, :created_at, :updated_at)'
        );
        $stmt->execute([
            'email' => $email,
            'mobile' => $mobile,
            'password_hash' => $this->passwordHash,
            'platform_role' => $platformRole,
            'email_verified_at' => $this->now,
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $userId = (int) $this->pdo->lastInsertId();
        $this->userIds[$key] = $userId;

        $profileStmt = $this->pdo->prepare(
            'INSERT INTO user_profiles (user_id, first_name, last_name, id_number, suburb, city, province, postal_code,
                country_code, created_at, updated_at)
             VALUES (:user_id, :first_name, :last_name, :id_number, :suburb, :city, :province, :postal_code, :country_code,
                :created_at, :updated_at)'
        );
        $profileStmt->execute([
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

    private function seedStokvelUsersAndTenants(): void
    {
        $ubuntuMembers = [
            ['thabo.mokoena@example.co.za', '0823112233', 'Thabo', 'Mokoena', '7803125800085', 'ubuntu_chair', 'chairperson', 'UB001'],
            ['nomsa.dlamini@example.co.za', '0834223344', 'Nomsa', 'Dlamini', '8209085800082', 'ubuntu_treasurer', 'treasurer', 'UB002'],
            ['sipho.ndlovu@example.co.za', '0845334455', 'Sipho', 'Ndlovu', '9001015800089', 'ubuntu_secretary', 'secretary', 'UB003'],
            ['lerato.pillay@example.co.za', '0856445566', 'Lerato', 'Pillay', '9507075800081', 'ubuntu_member1', 'ordinary_member', 'UB004'],
            ['david.vanwyk@example.co.za', '0867556677', 'David', 'Van Wyk', '8804155800083', 'ubuntu_member2', 'ordinary_member', 'UB005'],
            ['zanele.khumalo@example.co.za', '0878667788', 'Zanele', 'Khumalo', '9302225800086', 'ubuntu_member3', 'ordinary_member', 'UB006'],
        ];

        $masakhaneMembers = [
            ['themba.zulu@example.co.za', '0721001100', 'Themba', 'Zulu', '7706015800080', 'masa_chair', 'chairperson', 'MK001'],
            ['patience.molefe@example.co.za', '0732002200', 'Patience', 'Molefe', '8103035800084', 'masa_treasurer', 'treasurer', 'MK002'],
            ['grace.mthembu@example.co.za', '0743003300', 'Grace', 'Mthembu', '8605055800087', 'masa_secretary', 'secretary', 'MK003'],
            ['john.sithole@example.co.za', '0754004400', 'John', 'Sithole', '8907075800082', 'masa_member1', 'ordinary_member', 'MK004'],
            ['mary.naidoo@example.co.za', '0765005500', 'Mary', 'Naidoo', '9109095800085', 'masa_member2', 'ordinary_member', 'MK005'],
            ['peter.mabaso@example.co.za', '0776006600', 'Peter', 'Mabaso', '9401115800088', 'masa_member3', 'ordinary_member', 'MK006'],
        ];

        foreach ($ubuntuMembers as $member) {
            $this->createUser($member[0], $member[1], null, $member[2], $member[3], $member[4], $member[5]);
        }
        foreach ($masakhaneMembers as $member) {
            $this->createUser($member[0], $member[1], null, $member[2], $member[3], $member[4], $member[5]);
        }

        $this->createStokvel(
            'ubuntu',
            'a1111111-1111-4111-8111-111111111111',
            'Ubuntu Savings Club',
            'ubuntu-savings',
            'general_savings',
            'UBU-2020-0142',
            $this->userIds['ubuntu_chair'],
            true
        );

        $this->createStokvel(
            'masakhane',
            'b2222222-2222-4222-8222-222222222222',
            'Masakhane Burial Society',
            'masakhane-burial',
            'burial_society',
            'MSK-2018-0097',
            $this->userIds['masa_chair'],
            false
        );

        $this->assignMemberships('ubuntu', $ubuntuMembers);
        $this->assignMemberships('masakhane', $masakhaneMembers);
    }

    private function createStokvel(
        string $key,
        string $uuid,
        string $name,
        string $slug,
        string $type,
        string $registrationNumber,
        int $createdByUserId,
        bool $loansEnabled
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO stokvels (uuid, name, slug, stokvel_type, registration_number, description, status, activated_at,
                created_by_user_id, created_at, updated_at)
             VALUES (:uuid, :name, :slug, :stokvel_type, :registration_number, :description, :status, :activated_at,
                :created_by_user_id, :created_at, :updated_at)'
        );
        $stmt->execute([
            'uuid' => $uuid,
            'name' => $name,
            'slug' => $slug,
            'stokvel_type' => $type,
            'registration_number' => $registrationNumber,
            'description' => $name . ' — demo stokvel for local development.',
            'status' => 'active',
            'activated_at' => $this->now,
            'created_by_user_id' => $createdByUserId,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $stokvelId = (int) $this->pdo->lastInsertId();
        $this->stokvelIds[$key] = $stokvelId;

        $settings = $this->pdo->prepare(
            'INSERT INTO stokvel_settings (stokvel_id, contribution_day, contribution_frequency, grace_period_days,
                require_dual_approval, min_members, loans_enabled, payout_mode, bank_name, bank_account_name,
                bank_account_number_masked, bank_branch_code, created_at, updated_at)
             VALUES (:stokvel_id, 25, :frequency, 7, 1, 5, :loans_enabled, :payout_mode, :bank_name, :bank_account_name,
                :bank_account_number_masked, :bank_branch_code, :created_at, :updated_at)'
        );
        $settings->execute([
            'stokvel_id' => $stokvelId,
            'frequency' => 'monthly',
            'loans_enabled' => $loansEnabled ? 1 : 0,
            'payout_mode' => $type === 'burial_society' ? 'benefit' : 'rotational',
            'bank_name' => 'FNB',
            'bank_account_name' => $name,
            'bank_account_number_masked' => '****' . substr((string) random_int(1000, 9999), 0, 4),
            'bank_branch_code' => '250655',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $onboarding = $this->pdo->prepare(
            'INSERT INTO stokvel_onboarding (stokvel_id, current_step, steps_completed, completed_at, created_at, updated_at)
             VALUES (:stokvel_id, 12, :steps_completed, :completed_at, :created_at, :updated_at)'
        );
        $onboarding->execute([
            'stokvel_id' => $stokvelId,
            'steps_completed' => json_encode(range(1, 12), JSON_THROW_ON_ERROR),
            'completed_at' => $this->now,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string}> $members
     */
    private function assignMemberships(string $stokvelKey, array $members): void
    {
        $stokvelId = $this->stokvelIds[$stokvelKey];

        $membershipStmt = $this->pdo->prepare(
            'INSERT INTO memberships (stokvel_id, user_id, member_number, status, joined_at, approved_at, created_at, updated_at)
             VALUES (:stokvel_id, :user_id, :member_number, :status, :joined_at, :approved_at, :created_at, :updated_at)'
        );

        $roleStmt = $this->pdo->prepare(
            'INSERT INTO membership_roles (stokvel_id, membership_id, role_id, assigned_at, created_at)
             VALUES (:stokvel_id, :membership_id, :role_id, :assigned_at, :created_at)'
        );

        $consentStmt = $this->pdo->prepare(
            'INSERT INTO consent_records (stokvel_id, user_id, consent_type, granted, consent_text_version, ip_address, recorded_at, created_at)
             VALUES (:stokvel_id, :user_id, :consent_type, 1, :consent_text_version, :ip_address, :recorded_at, :created_at)'
        );

        foreach ($members as $member) {
            [, , , , , $userKey, $roleCode, $memberNumber] = $member;
            $userId = $this->userIds[$userKey];

            $membershipStmt->execute([
                'stokvel_id' => $stokvelId,
                'user_id' => $userId,
                'member_number' => $memberNumber,
                'status' => 'active',
                'joined_at' => $this->now,
                'approved_at' => $this->now,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
            $membershipId = (int) $this->pdo->lastInsertId();
            $this->membershipIds[$stokvelKey . '_' . $userKey] = $membershipId;

            $roleStmt->execute([
                'stokvel_id' => $stokvelId,
                'membership_id' => $membershipId,
                'role_id' => $this->roleIds[$roleCode],
                'assigned_at' => $this->now,
                'created_at' => $this->now,
            ]);

            $consentStmt->execute([
                'stokvel_id' => $stokvelId,
                'user_id' => $userId,
                'consent_type' => 'popia_processing',
                'consent_text_version' => '2026-01',
                'ip_address' => '127.0.0.1',
                'recorded_at' => $this->now,
                'created_at' => $this->now,
            ]);
        }
    }

    private function seedFinanceAndOperations(): void
    {
        $this->seedStokvelFinance('ubuntu', 500.00, true);
        $this->seedStokvelFinance('masakhane', 350.00, false);
    }

    private function seedStokvelFinance(string $stokvelKey, float $contributionAmount, bool $withLoan): void
    {
        $stokvelId = $this->stokvelIds[$stokvelKey];
        $prefix = strtoupper(substr($stokvelKey, 0, 2));

        $feeStructureStmt = $this->pdo->prepare(
            'INSERT INTO fee_structures (stokvel_id, code, name, structure_type, status, created_at, updated_at)
             VALUES (:stokvel_id, :code, :name, :structure_type, :status, :created_at, :updated_at)'
        );
        $feeStructureStmt->execute([
            'stokvel_id' => $stokvelId,
            'code' => 'monthly_contribution',
            'name' => 'Monthly Contribution',
            'structure_type' => 'contribution',
            'status' => 'active',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $feeStructureId = (int) $this->pdo->lastInsertId();

        $versionStmt = $this->pdo->prepare(
            'INSERT INTO fee_structure_versions (stokvel_id, fee_structure_id, version_number, amount, frequency,
                effective_from, grace_days, created_at)
             VALUES (:stokvel_id, :fee_structure_id, 1, :amount, :frequency, :effective_from, 7, :created_at)'
        );
        $versionStmt->execute([
            'stokvel_id' => $stokvelId,
            'fee_structure_id' => $feeStructureId,
            'amount' => number_format($contributionAmount, 2, '.', ''),
            'frequency' => 'monthly',
            'effective_from' => '2026-01-01',
            'created_at' => $this->now,
        ]);
        $feeVersionId = (int) $this->pdo->lastInsertId();

        $expenseCategoryStmt = $this->pdo->prepare(
            'INSERT INTO expense_categories (stokvel_id, code, name, created_at, updated_at)
             VALUES (:stokvel_id, :code, :name, :created_at, :updated_at)'
        );
        $expenseCategoryStmt->execute([
            'stokvel_id' => $stokvelId,
            'code' => 'admin',
            'name' => 'Administration',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $expenseCategoryId = (int) $this->pdo->lastInsertId();

        $coaStmt = $this->pdo->prepare(
            'INSERT INTO chart_of_accounts (stokvel_id, code, name, account_type, is_system, created_at, updated_at)
             VALUES (:stokvel_id, :code, :name, :account_type, 1, :created_at, :updated_at)'
        );
        foreach (
            [
                ['1000', 'Cash on Hand', 'asset'],
                ['2000', 'Member Contributions', 'liability'],
                ['4000', 'Administration Expense', 'expense'],
            ] as [$code, $name, $type]
        ) {
            $coaStmt->execute([
                'stokvel_id' => $stokvelId,
                'code' => $code,
                'name' => $name,
                'account_type' => $type,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        $memberKeys = array_filter(
            array_keys($this->membershipIds),
            static fn (string $k): bool => str_starts_with($k, $stokvelKey . '_')
        );

        $chargeStmt = $this->pdo->prepare(
            'INSERT INTO member_charges (stokvel_id, membership_id, fee_structure_version_id, charge_code, description,
                amount, amount_paid, due_date, status, period_start, period_end, created_at, updated_at)
             VALUES (:stokvel_id, :membership_id, :fee_structure_version_id, :charge_code, :description, :amount,
                :amount_paid, :due_date, :status, :period_start, :period_end, :created_at, :updated_at)'
        );

        $paymentStmt = $this->pdo->prepare(
            'INSERT INTO payment_transactions (stokvel_id, membership_id, transaction_ref, amount, payment_method, status,
                paid_at, recorded_by_user_id, created_at, updated_at)
             VALUES (:stokvel_id, :membership_id, :transaction_ref, :amount, :payment_method, :status, :paid_at,
                :recorded_by_user_id, :created_at, :updated_at)'
        );

        $allocationStmt = $this->pdo->prepare(
            'INSERT INTO payment_allocations (stokvel_id, payment_transaction_id, member_charge_id, amount, created_at)
             VALUES (:stokvel_id, :payment_transaction_id, :member_charge_id, :amount, :created_at)'
        );

        $treasurerUserId = $this->userIds[$stokvelKey === 'ubuntu' ? 'ubuntu_treasurer' : 'masa_treasurer'];
        $amountStr = number_format($contributionAmount, 2, '.', '');
        $chargeIndex = 1;

        foreach ($memberKeys as $membershipKey) {
            $membershipId = $this->membershipIds[$membershipKey];
            $chargeCode = $prefix . '-CHG-' . str_pad((string) $chargeIndex, 4, '0', STR_PAD_LEFT);
            $chargeIndex++;

            $chargeStmt->execute([
                'stokvel_id' => $stokvelId,
                'membership_id' => $membershipId,
                'fee_structure_version_id' => $feeVersionId,
                'charge_code' => $chargeCode,
                'description' => 'January 2026 monthly contribution',
                'amount' => $amountStr,
                'amount_paid' => $amountStr,
                'due_date' => '2026-01-25',
                'status' => 'paid',
                'period_start' => '2026-01-01',
                'period_end' => '2026-01-31',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
            $chargeId = (int) $this->pdo->lastInsertId();

            $paymentRef = $prefix . '-PAY-' . str_pad((string) ($chargeIndex - 1), 4, '0', STR_PAD_LEFT);
            $paymentStmt->execute([
                'stokvel_id' => $stokvelId,
                'membership_id' => $membershipId,
                'transaction_ref' => $paymentRef,
                'amount' => $amountStr,
                'payment_method' => 'eft',
                'status' => 'completed',
                'paid_at' => $this->now,
                'recorded_by_user_id' => $treasurerUserId,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
            $paymentId = (int) $this->pdo->lastInsertId();

            $allocationStmt->execute([
                'stokvel_id' => $stokvelId,
                'payment_transaction_id' => $paymentId,
                'member_charge_id' => $chargeId,
                'amount' => $amountStr,
                'created_at' => $this->now,
            ]);
        }

        $expenseStmt = $this->pdo->prepare(
            'INSERT INTO expenses (stokvel_id, expense_category_id, expense_ref, description, amount, status, incurred_at,
                paid_at, vendor_name, recorded_by_user_id, approved_by_user_id, created_at, updated_at)
             VALUES (:stokvel_id, :expense_category_id, :expense_ref, :description, :amount, :status, :incurred_at,
                :paid_at, :vendor_name, :recorded_by_user_id, :approved_by_user_id, :created_at, :updated_at)'
        );
        $expenseStmt->execute([
            'stokvel_id' => $stokvelId,
            'expense_category_id' => $expenseCategoryId,
            'expense_ref' => $prefix . '-EXP-0001',
            'description' => 'Stationery and meeting refreshments',
            'amount' => '450.00',
            'status' => 'paid',
            'incurred_at' => '2026-01-15',
            'paid_at' => $this->now,
            'vendor_name' => 'Pick n Pay Sandton',
            'recorded_by_user_id' => $treasurerUserId,
            'approved_by_user_id' => $this->userIds[$stokvelKey === 'ubuntu' ? 'ubuntu_chair' : 'masa_chair'],
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $chairMembershipId = $this->membershipIds[$stokvelKey . '_' . ($stokvelKey === 'ubuntu' ? 'ubuntu_chair' : 'masa_chair')];

        $meetingStmt = $this->pdo->prepare(
            'INSERT INTO meetings (stokvel_id, meeting_ref, title, meeting_type, status, scheduled_at, venue,
                chaired_by_membership_id, created_by_user_id, created_at, updated_at)
             VALUES (:stokvel_id, :meeting_ref, :title, :meeting_type, :status, :scheduled_at, :venue,
                :chaired_by_membership_id, :created_by_user_id, :created_at, :updated_at)'
        );
        $meetingStmt->execute([
            'stokvel_id' => $stokvelId,
            'meeting_ref' => $prefix . '-MTG-2026-01',
            'title' => 'January 2026 Monthly Meeting',
            'meeting_type' => 'regular',
            'status' => 'completed',
            'scheduled_at' => '2026-01-18 10:00:00',
            'venue' => 'Community Hall, Soweto',
            'chaired_by_membership_id' => $chairMembershipId,
            'created_by_user_id' => $this->userIds[$stokvelKey === 'ubuntu' ? 'ubuntu_secretary' : 'masa_secretary'],
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $meetingId = (int) $this->pdo->lastInsertId();

        $attendanceStmt = $this->pdo->prepare(
            'INSERT INTO meeting_attendance (stokvel_id, meeting_id, membership_id, attendance_status, recorded_at, created_at)
             VALUES (:stokvel_id, :meeting_id, :membership_id, :attendance_status, :recorded_at, :created_at)'
        );
        foreach ($memberKeys as $membershipKey) {
            $attendanceStmt->execute([
                'stokvel_id' => $stokvelId,
                'meeting_id' => $meetingId,
                'membership_id' => $this->membershipIds[$membershipKey],
                'attendance_status' => 'present',
                'recorded_at' => $this->now,
                'created_at' => $this->now,
            ]);
        }

        $payoutAmount = number_format($contributionAmount * 6, 2, '.', '');
        $cycleStmt = $this->pdo->prepare(
            'INSERT INTO payout_cycles (stokvel_id, cycle_code, name, status, start_date, payout_amount, created_at, updated_at)
             VALUES (:stokvel_id, :cycle_code, :name, :status, :start_date, :payout_amount, :created_at, :updated_at)'
        );
        $cycleStmt->execute([
            'stokvel_id' => $stokvelId,
            'cycle_code' => '2026-CYCLE-01',
            'name' => '2026 Rotation Cycle 1',
            'status' => 'active',
            'start_date' => '2026-01-01',
            'payout_amount' => $payoutAmount,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $cycleId = (int) $this->pdo->lastInsertId();

        $scheduleStmt = $this->pdo->prepare(
            'INSERT INTO payout_schedule (stokvel_id, payout_cycle_id, membership_id, sequence_number, scheduled_date,
                payout_amount, status, created_at, updated_at)
             VALUES (:stokvel_id, :payout_cycle_id, :membership_id, :sequence_number, :scheduled_date, :payout_amount,
                :status, :created_at, :updated_at)'
        );

        $sequence = 1;
        foreach ($memberKeys as $membershipKey) {
            $scheduleStmt->execute([
                'stokvel_id' => $stokvelId,
                'payout_cycle_id' => $cycleId,
                'membership_id' => $this->membershipIds[$membershipKey],
                'sequence_number' => $sequence,
                'scheduled_date' => sprintf('2026-%02d-25', min($sequence, 12)),
                'payout_amount' => $payoutAmount,
                'status' => $sequence === 1 ? 'scheduled' : 'pending',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
            $sequence++;
        }

        if ($withLoan) {
            $this->seedUbuntuLoan($stokvelId, $prefix, $treasurerUserId);
        }
    }

    private function seedUbuntuLoan(int $stokvelId, string $prefix, int $treasurerUserId): void
    {
        $productStmt = $this->pdo->prepare(
            'INSERT INTO loan_products (stokvel_id, code, name, status, min_amount, max_amount, interest_rate_percent,
                max_term_months, requires_guarantor, created_at, updated_at)
             VALUES (:stokvel_id, :code, :name, :status, :min_amount, :max_amount, :interest_rate_percent,
                :max_term_months, 1, :created_at, :updated_at)'
        );
        $productStmt->execute([
            'stokvel_id' => $stokvelId,
            'code' => 'emergency_loan',
            'name' => 'Emergency Member Loan',
            'status' => 'active',
            'min_amount' => '1000.00',
            'max_amount' => '10000.00',
            'interest_rate_percent' => '5.0000',
            'max_term_months' => 6,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $productId = (int) $this->pdo->lastInsertId();

        $borrowerMembershipId = $this->membershipIds['ubuntu_ubuntu_member1'];
        $guarantorMembershipId = $this->membershipIds['ubuntu_ubuntu_member2'];

        $applicationStmt = $this->pdo->prepare(
            'INSERT INTO loan_applications (stokvel_id, membership_id, loan_product_id, application_ref, requested_amount,
                term_months, purpose, status, submitted_at, decided_at, decided_by_user_id, created_at, updated_at)
             VALUES (:stokvel_id, :membership_id, :loan_product_id, :application_ref, :requested_amount, :term_months,
                :purpose, :status, :submitted_at, :decided_at, :decided_by_user_id, :created_at, :updated_at)'
        );
        $applicationStmt->execute([
            'stokvel_id' => $stokvelId,
            'membership_id' => $borrowerMembershipId,
            'loan_product_id' => $productId,
            'application_ref' => $prefix . '-LN-APP-0001',
            'requested_amount' => '5000.00',
            'term_months' => 5,
            'purpose' => 'School fees assistance for dependent at Orlando West Secondary School.',
            'status' => 'approved',
            'submitted_at' => '2026-01-10 09:30:00',
            'decided_at' => $this->now,
            'decided_by_user_id' => $treasurerUserId,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $applicationId = (int) $this->pdo->lastInsertId();

        $guarantorStmt = $this->pdo->prepare(
            'INSERT INTO loan_guarantors (stokvel_id, loan_application_id, guarantor_membership_id, guaranteed_amount,
                status, accepted_at, created_at, updated_at)
             VALUES (:stokvel_id, :loan_application_id, :guarantor_membership_id, :guaranteed_amount, :status, :accepted_at,
                :created_at, :updated_at)'
        );
        $guarantorStmt->execute([
            'stokvel_id' => $stokvelId,
            'loan_application_id' => $applicationId,
            'guarantor_membership_id' => $guarantorMembershipId,
            'guaranteed_amount' => '5000.00',
            'status' => 'accepted',
            'accepted_at' => $this->now,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $loanStmt = $this->pdo->prepare(
            'INSERT INTO loans (stokvel_id, loan_application_id, membership_id, loan_product_id, loan_ref, principal_amount,
                interest_rate_percent, term_months, status, disbursed_at, disbursed_amount, outstanding_balance,
                created_at, updated_at)
             VALUES (:stokvel_id, :loan_application_id, :membership_id, :loan_product_id, :loan_ref, :principal_amount,
                :interest_rate_percent, :term_months, :status, :disbursed_at, :disbursed_amount, :outstanding_balance,
                :created_at, :updated_at)'
        );
        $loanStmt->execute([
            'stokvel_id' => $stokvelId,
            'loan_application_id' => $applicationId,
            'membership_id' => $borrowerMembershipId,
            'loan_product_id' => $productId,
            'loan_ref' => $prefix . '-LN-0001',
            'principal_amount' => '5000.00',
            'interest_rate_percent' => '5.0000',
            'term_months' => 5,
            'status' => 'active',
            'disbursed_at' => $this->now,
            'disbursed_amount' => '5000.00',
            'outstanding_balance' => '4200.00',
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $loanId = (int) $this->pdo->lastInsertId();

        $scheduleStmt = $this->pdo->prepare(
            'INSERT INTO loan_schedules (stokvel_id, loan_id, instalment_number, due_date, principal_due, interest_due,
                total_due, amount_paid, status, paid_at, created_at, updated_at)
             VALUES (:stokvel_id, :loan_id, :instalment_number, :due_date, :principal_due, :interest_due, :total_due,
                :amount_paid, :status, :paid_at, :created_at, :updated_at)'
        );
        $scheduleStmt->execute([
            'stokvel_id' => $stokvelId,
            'loan_id' => $loanId,
            'instalment_number' => 1,
            'due_date' => '2026-02-25',
            'principal_due' => '960.00',
            'interest_due' => '40.00',
            'total_due' => '1000.00',
            'amount_paid' => '1000.00',
            'status' => 'paid',
            'paid_at' => $this->now,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
        $scheduleId = (int) $this->pdo->lastInsertId();

        $repaymentStmt = $this->pdo->prepare(
            'INSERT INTO loan_repayments (stokvel_id, loan_id, loan_schedule_id, repayment_ref, amount, payment_method,
                paid_at, recorded_by_user_id, created_at)
             VALUES (:stokvel_id, :loan_id, :loan_schedule_id, :repayment_ref, :amount, :payment_method, :paid_at,
                :recorded_by_user_id, :created_at)'
        );
        $repaymentStmt->execute([
            'stokvel_id' => $stokvelId,
            'loan_id' => $loanId,
            'loan_schedule_id' => $scheduleId,
            'repayment_ref' => $prefix . '-LN-REP-0001',
            'amount' => '1000.00',
            'payment_method' => 'debit_order',
            'paid_at' => $this->now,
            'recorded_by_user_id' => $treasurerUserId,
            'created_at' => $this->now,
        ]);

        for ($i = 2; $i <= 5; $i++) {
            $scheduleStmt->execute([
                'stokvel_id' => $stokvelId,
                'loan_id' => $loanId,
                'instalment_number' => $i,
                'due_date' => sprintf('2026-%02d-25', $i + 1),
                'principal_due' => '1000.00',
                'interest_due' => '0.00',
                'total_due' => '1000.00',
                'amount_paid' => '0.00',
                'status' => 'pending',
                'paid_at' => null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }
}
