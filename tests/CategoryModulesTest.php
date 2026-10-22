<?php

declare(strict_types=1);

use App\Services\Auth\AuthService;
use App\Core\App;
use Tests\Support\HttpClient;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $login = static function (string $email): AuthService {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login($email, 'Password123!');
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        test_assert($stokvel !== null);
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        return $auth;
    };

    $runner->add('Payout categories page loads with seeded Burial/Grocery', function () use ($login): void {
        $auth = $login('thabo.mokoena@example.co.za');
        $client = new HttpClient();
        $res = $client->get('/app/payouts/categories');
        test_assert(in_array($res['status'], [200, 302], true), 'Status ' . $res['status']);
        if ($res['status'] === 200) {
            test_assert(
                str_contains($res['body'], 'Burial') || str_contains($res['body'], 'burial'),
                'Expected Burial category'
            );
            test_assert(
                str_contains($res['body'], 'Grocery') || str_contains($res['body'], 'grocery'),
                'Expected Grocery category'
            );
        }
        $auth->logout();
    }, 'http');

    $runner->add('Document categories page loads for secretary', function () use ($login): void {
        $auth = $login('sipho.ndlovu@example.co.za');
        $client = new HttpClient();
        $res = $client->get('/app/documents/categories');
        test_assert(in_array($res['status'], [200, 302], true), 'Status ' . $res['status']);
        $auth->logout();
    }, 'http');

    $runner->add('Settings roles page loads for chairperson', function () use ($login): void {
        $auth = $login('thabo.mokoena@example.co.za');
        $client = new HttpClient();
        $res = $client->get('/app/settings/roles');
        test_assert($res['status'] === 200, 'Roles page status ' . $res['status']);
        test_assert(str_contains($res['body'], 'permissions') || str_contains($res['body'], 'Chairperson'), 'Roles content missing');
        $auth->logout();
    }, 'http');

    $runner->add('Beneficiaries index loads for secretary', function () use ($login): void {
        $auth = $login('sipho.ndlovu@example.co.za');
        $client = new HttpClient();
        $res = $client->get('/app/beneficiaries');
        test_assert(in_array($res['status'], [200, 302], true), 'Status ' . $res['status']);
        $auth->logout();
    }, 'http');

    $runner->add('Ordinary member denied beneficiaries manage create', function () use ($login): void {
        $auth = $login('lerato.pillay@example.co.za');
        $client = new HttpClient();
        $res = $client->get('/app/beneficiaries/create');
        test_assert(in_array($res['status'], [302, 403], true), 'Member should be denied create, got ' . $res['status']);
        $auth->logout();
    }, 'security');
};
