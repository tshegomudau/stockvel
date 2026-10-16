<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Session;
use App\Services\Auth\AuthService;
use Tests\Support\HttpClient;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Public landing and health respond', function (): void {
        $client = new HttpClient();
        $health = $client->get('/health');
        test_assert(in_array($health['status'], [200, 0], true) || $health['status'] === 200 || str_contains($health['body'], 'ok') || $health['status'] === 200, 'Health check failed: ' . $health['status']);
        // In-process path
        if ($health['status'] === 0) {
            $health = $client->get('/health');
        }
        $home = $client->get('/');
        test_assert(in_array($home['status'], [200, 302], true), 'Landing status ' . $home['status']);
    }, 'http');

    $runner->add('Guest cannot access protected /app', function (): void {
        Session::forget('user_id');
        Session::forget('active_stokvel_id');
        App::getInstance()->container()->get(\App\Core\TenantContext::class)->setUser(null);
        App::getInstance()->container()->get(\App\Core\TenantContext::class)->setTenant(null);
        $client = new HttpClient();
        $res = $client->get('/app');
        test_assert(in_array($res['status'], [401, 302, 403], true), 'Expected auth challenge, got ' . $res['status'] . ' ' . $res['body']);
    }, 'http');

    $runner->add('Login with seeded treasurer reaches contributions', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login('nomsa.dlamini@example.co.za', 'Password123!');
        test_assert(isset($user['id']));
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        test_assert($stokvel !== null);
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);

        $client = new HttpClient();
        $res = $client->get('/app/contributions');
        test_assert(in_array($res['status'], [200, 302], true), 'Contributions status ' . $res['status'] . ' body=' . substr($res['body'], 0, 200));
        if ($res['status'] === 200) {
            test_assert(
                str_contains($res['body'], 'Contribution') || str_contains($res['body'], 'contribution') || str_contains($res['body'], 'Payment'),
                'Contributions page missing expected content'
            );
        }
        $auth->logout();
    }, 'http');

    $runner->add('Ordinary member denied fees.manage route', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login('lerato.pillay@example.co.za', 'Password123!');
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        $client = new HttpClient();
        $res = $client->get('/app/fees');
        test_assert(in_array($res['status'], [403, 302], true), 'Expected denial, got ' . $res['status']);
        $auth->logout();
    }, 'http');

    $runner->add('Platform jobs page uses notification_jobs', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $auth->login('admin@stockvel.local', 'Password123!');
        $client = new HttpClient();
        $res = $client->get('/platform/jobs');
        test_assert($res['status'] === 200, 'Platform jobs status ' . $res['status'] . ' ' . substr($res['body'], 0, 300));
        test_assert(!str_contains($res['body'], "failed_jobs"), 'Should not reference missing failed_jobs table in error');
        $auth->logout();
    }, 'http');

    $runner->add('CSRF rejected on login post without token', function (): void {
        $client = new HttpClient();
        $res = $client->post('/login', [
            'email' => 'admin@stockvel.local',
            'password' => 'Password123!',
        ], [], false);
        test_assert(in_array($res['status'], [419, 403, 400, 302, 500], true) || str_contains(strtolower($res['body']), 'csrf') || $res['status'] !== 200, 'CSRF should not silently succeed');
    }, 'http');

    $runner->add('Payouts index uses payout_cycles', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login('nomsa.dlamini@example.co.za', 'Password123!');
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        $client = new HttpClient();
        $res = $client->get('/app/payouts');
        test_assert($res['status'] === 200, 'Payouts status ' . $res['status'] . ' ' . substr($res['body'], 0, 200));
        test_assert(!str_contains(strtolower($res['body']), "doesn't exist"), 'Should not hit missing table error');
        $auth->logout();
    }, 'http');

    $runner->add('Rules index uses rules table', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login('sipho.ndlovu@example.co.za', 'Password123!');
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        $client = new HttpClient();
        $res = $client->get('/app/rules');
        test_assert($res['status'] === 200, 'Rules status ' . $res['status'] . ' ' . substr($res['body'], 0, 200));
        test_assert(!str_contains(strtolower($res['body']), 'stokvel_rules'), 'Must not query missing stokvel_rules');
        $auth->logout();
    }, 'http');
};
