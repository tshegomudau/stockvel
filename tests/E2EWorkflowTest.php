<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Auth\AuthService;
use App\Services\Finance\ContributionService;
use App\Services\Finance\LedgerService;
use App\Services\PermissionService;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('E2E seed matrix accounts exist', function (): void {
        $emails = [
            'admin@stockvel.local',
            'support@stockvel.local',
            'auditor@stockvel.local',
            'thabo.mokoena@example.co.za',
            'nomsa.dlamini@example.co.za',
            'dual.member@example.co.za',
            'unverified@example.co.za',
            'locked@example.co.za',
            'suspended.ubuntu@example.co.za',
            'custom.perms@example.co.za',
            'noreports@example.co.za',
            'committee.ubuntu@example.co.za',
            'stokvel.auditor.ubuntu@example.co.za',
        ];
        foreach ($emails as $email) {
            $row = test_db()->fetch('SELECT id FROM users WHERE email = :e LIMIT 1', ['e' => $email]);
            test_assert($row !== null, 'Missing seeded user ' . $email);
        }
        $invites = (int) (test_db()->fetch('SELECT COUNT(*) AS c FROM invitations')['c'] ?? 0);
        test_assert($invites >= 2, 'Expected valid and expired invitations');
    }, 'e2e');

    $runner->add('Dual membership tenant switch changes context', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login('dual.member@example.co.za', 'Password123!');
        $ubuntu = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        $masa = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'masakhane-burial' LIMIT 1");
        $auth->switchStokvel((int) $ubuntu['id'], (int) $user['id']);
        $tenant = App::getInstance()->container()->get(\App\Core\TenantContext::class);
        test_assert($tenant->stokvelId() === (int) $ubuntu['id']);
        $permsA = $tenant->permissions();
        $auth->switchStokvel((int) $masa['id'], (int) $user['id']);
        test_assert($tenant->stokvelId() === (int) $masa['id']);
        $permsB = $tenant->permissions();
        test_assert($permsA !== $permsB || in_array('*', $permsB, true) || in_array('contributions.capture', $permsB, true), 'Expected treasurer perms on Masakhane');
        $auth->logout();
    }, 'e2e');

    $runner->add('Cross-tenant membership read returns null', function (): void {
        $ubuntuMember = test_db()->fetch(
            "SELECT m.id, m.stokvel_id FROM memberships m
             INNER JOIN stokvels s ON s.id = m.stokvel_id
             WHERE s.slug = 'ubuntu-savings' LIMIT 1"
        );
        $masa = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'masakhane-burial' LIMIT 1");
        $row = test_db()->fetch(
            'SELECT id FROM memberships WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $ubuntuMember['id'], 'sid' => $masa['id']]
        );
        test_assert($row === null);
    }, 'e2e');

    $runner->add('Contribution capture posts balanced journal', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
            $member = $db->fetch(
                'SELECT id FROM memberships WHERE stokvel_id = :sid AND status = :st LIMIT 1',
                ['sid' => $stokvel['id'], 'st' => 'active']
            );
            $treasurer = $db->fetch("SELECT id FROM users WHERE email = 'nomsa.dlamini@example.co.za' LIMIT 1");
            $svc = App::getInstance()->container()->get(ContributionService::class);
            $payment = $svc->capturePayment(
                (int) $stokvel['id'],
                (int) $member['id'],
                '25.00',
                'cash',
                (int) $treasurer['id'],
                'e2e-pay-' . bin2hex(random_bytes(4))
            );
            test_assert(isset($payment['id']));
            $journalId = null;
            if (!empty($payment['notes'])) {
                $meta = json_decode((string) $payment['notes'], true);
                $journalId = isset($meta['journal_entry_id']) ? (int) $meta['journal_entry_id'] : null;
            }
            test_assert($journalId !== null && $journalId > 0, 'Expected journal_entry_id in payment notes');
            $ledger = App::getInstance()->container()->get(LedgerService::class);
            test_assert($ledger->validateBalance((int) $stokvel['id'], $journalId), 'Journal must balance');
            $entry = $ledger->getEntry((int) $stokvel['id'], $journalId);
            test_assert(($entry['status'] ?? '') === LedgerService::STATUS_POSTED);
        });
    }, 'e2e');

    $runner->add('Permission matrix: chairperson has wildcard', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login('thabo.mokoena@example.co.za', 'Password123!');
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        $perms = App::getInstance()->container()->get(\App\Core\TenantContext::class)->permissions();
        test_assert(in_array('*', $perms, true) || App::getInstance()->container()->get(\App\Core\TenantContext::class)->can('settings.manage'));
        $auth->logout();
    }, 'e2e');

    $runner->add('No-reports user lacks reports.view', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login('noreports@example.co.za', 'Password123!');
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        $can = App::getInstance()->container()->get(\App\Core\TenantContext::class)->can('reports.view');
        test_assert($can === false, 'noreports user should not view reports');
        $auth->logout();
    }, 'e2e');

    $runner->add('Platform support cannot mutate contributions permission', function (): void {
        $svc = App::getInstance()->container()->get(PermissionService::class);
        $perms = $svc->forPlatformRole('platform_support');
        test_assert(!in_array('*', $perms, true));
        test_assert(!in_array('contributions.capture', $perms, true));
        test_assert(in_array('members.view', $perms, true));
    }, 'e2e');

    $runner->add('Cron notification processing is idempotent when empty', function (): void {
        $n1 = App::getInstance()->container()->get(\App\Services\Notifications\NotificationQueueService::class)->processDue();
        $n2 = App::getInstance()->container()->get(\App\Services\Notifications\NotificationQueueService::class)->processDue();
        test_assert(is_int($n1) && is_int($n2));
        test_assert($n2 === 0 || $n2 <= $n1);
    }, 'e2e');
};
