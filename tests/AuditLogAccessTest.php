<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Auth\AuthService;
use Tests\Support\HttpClient;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Stokvel chairperson cannot access platform audit logs', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $auth->login('nomsa.dlamini@example.co.za', 'Password123!');
        $client = new HttpClient();
        $res = $client->get('/platform/audit');
        test_assert(in_array((int) $res['status'], [401, 403], true), 'Chairperson expected 401/403, got ' . $res['status']);
        $auth->logout();
    }, 'http');

    $runner->add('Ordinary member cannot access platform audit logs', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        // Prefer a known ordinary member from demo seed
        try {
            $auth->login('thabo.mokoena@example.co.za', 'Password123!');
        } catch (\Throwable) {
            $auth->login('sipho.nkosi@example.co.za', 'Password123!');
        }
        $client = new HttpClient();
        $res = $client->get('/platform/audit');
        test_assert(in_array((int) $res['status'], [401, 403], true), 'Member expected 401/403, got ' . $res['status']);
        $auth->logout();
    }, 'http');

    $runner->add('Platform super admin with audit_logs.view can open audit', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $auth->login('admin@stockvel.local', 'Password123!');
        $client = new HttpClient();
        $res = $client->get('/platform/audit');
        test_assert((int) $res['status'] === 200, 'Platform admin audit expected 200, got ' . $res['status']);
        $auth->logout();
    }, 'http');

    $runner->add('Platform support without audit_logs.view is denied audit logs', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $auth->login('support@stockvel.local', 'Password123!');
        $client = new HttpClient();
        $res = $client->get('/platform/audit');
        test_assert(
            (int) $res['status'] === 403,
            'Platform support without audit_logs.view expected 403, got ' . $res['status']
        );
        $auth->logout();
    }, 'http');

    $runner->add('Platform auditor with audit_logs.view can open audit', function (): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $auth->login('auditor@stockvel.local', 'Password123!');
        $client = new HttpClient();
        $res = $client->get('/platform/audit');
        test_assert((int) $res['status'] === 200, 'Platform auditor expected 200, got ' . $res['status']);
        $auth->logout();
    }, 'http');
};
