<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Auth\AuthService;
use App\Services\Onboarding\OnboardingService;
use App\Services\Stokvel\StokvelCreationRequestService;
use Tests\Support\HttpClient;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('New stokvel starts with incomplete setup wizard status', function (): void {
        test_transaction(function (): void {
            $db = test_db();
            $admin = $db->fetch("SELECT id FROM users WHERE email = 'admin@stockvel.local' LIMIT 1");
            test_assert($admin !== null);
            /** @var StokvelCreationRequestService $svc */
            $svc = App::getInstance()->container()->get(StokvelCreationRequestService::class);
            $sid = $svc->createDirectByAdmin([
                'name' => 'Wizard Seed ' . bin2hex(random_bytes(3)),
                'stokvel_type' => 'general_savings',
            ], (int) $admin['id']);

            /** @var OnboardingService $onboarding */
            $onboarding = App::getInstance()->container()->get(OnboardingService::class);
            test_assert($onboarding->needsSetup($sid), 'New stokvel must require setup');
            test_assert(!$onboarding->isComplete($sid));

            $row = $db->fetch(
                'SELECT current_step, completed_at FROM stokvel_onboarding WHERE stokvel_id = :sid LIMIT 1',
                ['sid' => $sid]
            );
            test_assert($row !== null, 'Onboarding row must be created');
            test_assert($row['completed_at'] === null);
            test_assert((int) $row['current_step'] === 1);
        });
    }, 'integration');

    $runner->add('Legacy stokvel without onboarding row is treated as setup complete', function (): void {
        test_transaction(function (): void {
            $db = test_db();
            $sid = (int) $db->insert(
                'INSERT INTO stokvels (uuid, name, slug, status, timezone, currency_code, created_at, updated_at)
                 VALUES (:u, :n, :s, :st, :tz, :cc, :ca, :ua)',
                [
                    'u' => uuid(),
                    'n' => 'Legacy No Wizard',
                    's' => 'legacy-nw-' . bin2hex(random_bytes(3)),
                    'st' => 'active',
                    'tz' => 'Africa/Johannesburg',
                    'cc' => 'ZAR',
                    'ca' => now_utc(),
                    'ua' => now_utc(),
                ]
            );
            /** @var OnboardingService $onboarding */
            $onboarding = App::getInstance()->container()->get(OnboardingService::class);
            test_assert($onboarding->isComplete($sid), 'Missing row must grandfather as complete');
            test_assert(!$onboarding->needsSetup($sid));
        });
    }, 'integration');

    $runner->add('Chairperson home path lands on wizard until setup is complete', function (): void {
        test_transaction(function (): void {
            $db = test_db();
            $auth = App::getInstance()->container()->get(AuthService::class);
            $admin = $db->fetch("SELECT id FROM users WHERE email = 'admin@stockvel.local' LIMIT 1");
            /** @var StokvelCreationRequestService $svc */
            $svc = App::getInstance()->container()->get(StokvelCreationRequestService::class);
            $sid = $svc->createDirectByAdmin([
                'name' => 'Chair Wizard ' . bin2hex(random_bytes(3)),
            ], (int) $admin['id']);

            $email = 'wiz_chair_' . bin2hex(random_bytes(3)) . '@example.com';
            $user = $auth->register([
                'email' => $email,
                'password' => 'Password123!',
                'first_name' => 'Wiz',
                'last_name' => 'Chair',
            ]);
            $userId = (int) $user['id'];
            $now = now_utc();
            $membershipId = (int) $db->insert(
                'INSERT INTO memberships (stokvel_id, user_id, member_number, status, joined_at, approved_at, created_at, updated_at)
                 VALUES (:sid, :uid, :mn, :st, :ja, :aa, :ca, :ua)',
                [
                    'sid' => $sid,
                    'uid' => $userId,
                    'mn' => 'M-00001',
                    'st' => 'active',
                    'ja' => $now,
                    'aa' => $now,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
            $role = $db->fetch("SELECT id FROM roles WHERE code = 'chairperson' AND stokvel_id IS NULL LIMIT 1");
            test_assert($role !== null);
            $db->insert(
                'INSERT INTO membership_roles (stokvel_id, membership_id, role_id, assigned_by_user_id, assigned_at, created_at)
                 VALUES (:sid, :mid, :rid, :by, :aa, :ca)',
                [
                    'sid' => $sid,
                    'mid' => $membershipId,
                    'rid' => (int) $role['id'],
                    'by' => $userId,
                    'aa' => $now,
                    'ca' => $now,
                ]
            );

            $fresh = $db->fetch('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
            test_assert(home_path_for_user($fresh) === '/app/onboarding', 'Incomplete setup must land on wizard');

            /** @var OnboardingService $onboarding */
            $onboarding = App::getInstance()->container()->get(OnboardingService::class);
            // Advance to review, then complete.
            for ($step = 1; $step <= OnboardingService::MAX_STEP; $step++) {
                $onboarding->saveStep($sid, $step, ['notes' => 'ok'], $userId);
            }
            $onboarding->activate($sid, $userId);
            test_assert($onboarding->isComplete($sid));
            test_assert(home_path_for_user($fresh) === '/app', 'Completed setup must land on dashboard');
        });
    }, 'integration');

    $runner->add('Completed setup hides wizard route and shows dashboard', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $auth->login('thabo.mokoena@example.co.za', 'Password123!');
        $client = new HttpClient();
        $dash = $client->get('/app');
        // Seeded ubuntu chairperson is grandfathered/complete — must not be forced to wizard.
        test_assert((int) $dash['status'] === 200, 'Dashboard expected 200, got ' . $dash['status']);
        test_assert(
            !str_contains($dash['body'], 'Stokvel setup wizard')
            || str_contains($dash['body'], 'Dashboard'),
            'Completed chairperson should see dashboard not forced wizard content'
        );
        $wiz = $client->get('/app/onboarding');
        // Complete → redirect away from wizard.
        test_assert(
            in_array((int) $wiz['status'], [200, 302], true),
            'Wizard route should respond, got ' . $wiz['status']
        );
        if ((int) $wiz['status'] === 302) {
            test_assert(
                str_contains((string) ($wiz['location'] ?? ''), '/app'),
                'Completed wizard should redirect to app'
            );
        }
        $auth->logout();
    }, 'http');
};
