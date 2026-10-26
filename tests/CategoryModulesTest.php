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

    $runner->add('Loan purposes catalogue CRUD is tenant-scoped', function () use ($login): void {
        $auth = $login('thabo.mokoena@example.co.za');
        $client = new HttpClient();
        $list = $client->get('/app/loans/purposes');
        test_assert($list['status'] === 200, 'Loan purposes status ' . $list['status']);
        test_assert(
            str_contains($list['body'], 'Emergency') || str_contains($list['body'], 'emergency'),
            'Expected seeded Emergency purpose'
        );

        $code = 'qa_' . substr(bin2hex(random_bytes(3)), 0, 6);
        $create = $client->post('/app/loans/purposes', [
            'code' => $code,
            'name' => 'QA Purpose ' . $code,
            'sort_order' => '55',
        ]);
        test_assert($create['status'] === 302, 'Create purpose should redirect');

        $ubuntu = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        $masa = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'masakhane-burial' LIMIT 1");
        test_assert($ubuntu !== null && $masa !== null, 'Demo stokvels missing');
        $row = test_db()->fetch(
            'SELECT id, stokvel_id FROM loan_purposes WHERE code = :code AND stokvel_id = :sid LIMIT 1',
            ['code' => $code, 'sid' => (int) $ubuntu['id']]
        );
        test_assert($row !== null, 'Purpose not stored for ubuntu');

        $cross = test_db()->fetch(
            'SELECT id FROM loan_purposes WHERE code = :code AND stokvel_id = :sid LIMIT 1',
            ['code' => $code, 'sid' => (int) $masa['id']]
        );
        test_assert($cross === null, 'Purpose must not leak into other tenant');

        $deactivate = $client->post('/app/loans/purposes/' . (int) $row['id'] . '/deactivate', []);
        test_assert($deactivate['status'] === 302, 'Deactivate should redirect');
        $inactive = test_db()->fetch('SELECT is_active FROM loan_purposes WHERE id = :id', ['id' => (int) $row['id']]);
        test_assert((int) ($inactive['is_active'] ?? 1) === 0, 'Purpose should be inactive');

        $auth->logout();
    }, 'http');
};
