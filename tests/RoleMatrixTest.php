<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Auth\AuthService;
use Tests\Support\HttpClient;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $assertOk = static function (array $res, string $label): void {
        test_assert(
            in_array($res['status'], [200, 302], true),
            $label . ' expected 200/302, got ' . $res['status'] . ' ' . substr($res['body'], 0, 240)
        );
        $lower = strtolower($res['body']);
        test_assert(
            !str_contains($lower, "doesn't exist")
            && !str_contains($lower, 'base table or view not found')
            && !str_contains($lower, 'sqlstate['),
            $label . ' contains SQL error: ' . substr($res['body'], 0, 300)
        );
    };

    $loginAs = static function (string $email, string $slug = 'ubuntu-savings'): AuthService {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login($email, 'Password123!');
        $stokvel = test_db()->fetch('SELECT id FROM stokvels WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
        test_assert($stokvel !== null, 'Missing stokvel ' . $slug);
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        return $auth;
    };

    $runner->add('Chairperson core modules load without SQL errors', function () use ($loginAs, $assertOk): void {
        $auth = $loginAs('thabo.mokoena@example.co.za');
        $client = new HttpClient();
        foreach ([
            '/app',
            '/app/members',
            '/app/fees',
            '/app/contributions',
            '/app/expenses',
            '/app/expenses/categories',
            '/app/approvals',
            '/app/loans',
            '/app/loans/products',
            '/app/payouts',
            '/app/meetings',
            '/app/rules',
            '/app/documents',
            '/app/reports',
            '/app/support',
            '/app/burial-claims',
            '/app/grocery',
            '/app/notifications',
            '/app/settings',
        ] as $path) {
            $assertOk($client->get($path), 'Chairperson ' . $path);
        }
        $auth->logout();
    }, 'http');

    $runner->add('Treasurer finance modules load', function () use ($loginAs, $assertOk): void {
        $auth = $loginAs('nomsa.dlamini@example.co.za');
        $client = new HttpClient();
        foreach (['/app', '/app/contributions', '/app/fees', '/app/expenses', '/app/approvals', '/app/reports'] as $path) {
            $assertOk($client->get($path), 'Treasurer ' . $path);
        }
        $auth->logout();
    }, 'http');

    $runner->add('Secretary governance modules load', function () use ($loginAs, $assertOk): void {
        $auth = $loginAs('sipho.ndlovu@example.co.za');
        $client = new HttpClient();
        foreach (['/app', '/app/members', '/app/meetings', '/app/rules', '/app/documents'] as $path) {
            $assertOk($client->get($path), 'Secretary ' . $path);
        }
        $auth->logout();
    }, 'http');

    $runner->add('Ordinary member denied management routes', function () use ($loginAs): void {
        $auth = $loginAs('lerato.pillay@example.co.za');
        $client = new HttpClient();
        foreach (['/app/fees', '/app/settings', '/app/approvals'] as $path) {
            $res = $client->get($path);
            test_assert(
                in_array($res['status'], [403, 302], true),
                'Ordinary member should be denied ' . $path . ', got ' . $res['status']
            );
        }
        $member = $client->get('/member');
        test_assert(in_array($member['status'], [200, 302], true), 'Member dashboard status ' . $member['status']);
        $auth->logout();
    }, 'http');

    $runner->add('Stokvel B chairperson isolated dashboard loads', function () use ($loginAs, $assertOk): void {
        $auth = $loginAs('themba.zulu@example.co.za', 'masakhane-burial');
        $client = new HttpClient();
        $assertOk($client->get('/app'), 'Masakhane dashboard');
        $assertOk($client->get('/app/members'), 'Masakhane members');
        $assertOk($client->get('/app/approvals'), 'Masakhane approvals');
        $auth->logout();
    }, 'http');

    $runner->add('Platform admin dashboard and jobs load', function () use ($assertOk): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $auth->login('admin@stockvel.local', 'Password123!');
        $client = new HttpClient();
        $assertOk($client->get('/platform'), 'Platform dashboard');
        $assertOk($client->get('/platform/jobs'), 'Platform jobs');
        $assertOk($client->get('/platform/support'), 'Platform support');
        $assertOk($client->get('/platform/roles'), 'Platform roles');
        $assertOk($client->get('/platform/users'), 'Platform users');
        $assertOk($client->get('/platform/audit'), 'Platform audit');
        $auth->logout();
    }, 'http');

    $runner->add('Approvals page uses approval_requests', function () use ($loginAs, $assertOk): void {
        $auth = $loginAs('nomsa.dlamini@example.co.za');
        $client = new HttpClient();
        $res = $client->get('/app/approvals');
        $assertOk($res, 'Approvals index');
        test_assert(!str_contains(strtolower($res['body']), ' from approvals '), 'Must not query missing approvals table');
        $auth->logout();
    }, 'http');
};
