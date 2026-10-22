<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Auth\AuthService;
use App\Services\Finance\ChartOfAccountsService;
use App\Services\Membership\MemberChangeRequestService;
use App\Support\SensitiveData;
use Tests\Support\HttpClient;
use Tests\TestRunner;

/**
 * Regression coverage for product-upgrade modules (COA, support, loans products,
 * member change requests, burial/grocery, reports export, meetings notes).
 */
return static function (TestRunner $runner): void {
    $assertOk = static function (array $res, string $label): void {
        test_assert(
            in_array($res['status'], [200, 302], true),
            $label . ' expected 200/302, got ' . $res['status'] . ' ' . substr($res['body'], 0, 280)
        );
        $lower = strtolower($res['body']);
        test_assert(
            !str_contains($lower, "doesn't exist")
            && !str_contains($lower, 'base table or view not found')
            && !str_contains($lower, 'unknown column')
            && !str_contains($lower, 'sqlstate['),
            $label . ' contains SQL/schema error: ' . substr($res['body'], 0, 320)
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

    $runner->add('COA bank and loan_receivable accounts exist after ensure', function (): void {
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        test_assert($stokvel !== null);
        $sid = (int) $stokvel['id'];
        /** @var ChartOfAccountsService $coa */
        $coa = App::getInstance()->container()->get(ChartOfAccountsService::class);
        $coa->ensureDefaultAccounts($sid);
        $bank = $coa->requireBySubType($sid, 'bank');
        $loan = $coa->requireBySubType($sid, 'loan_receivable');
        test_assert(($bank['sub_type'] ?? '') === 'bank', 'bank sub_type missing');
        test_assert(($loan['sub_type'] ?? '') === 'loan_receivable', 'loan_receivable missing');
    }, 'e2e');

    $runner->add('Upgrade routes load for chairperson without SQL errors', function () use ($loginAs, $assertOk): void {
        $auth = $loginAs('thabo.mokoena@example.co.za');
        $client = new HttpClient();
        foreach ([
            '/app/support',
            '/app/support/create',
            '/app/expenses/categories',
            '/app/loans/products',
            '/app/loans/products/create',
            '/app/documents/create',
            '/app/contributions/import',
            '/app/contributions/import/template.csv',
            '/app/burial-claims',
            '/app/burial-claims/create',
            '/app/grocery',
            '/app/grocery/create',
            '/app/reports/contributions.csv',
            '/app/reports/contributions/export',
        ] as $path) {
            $assertOk($client->get($path), 'Chairperson ' . $path);
        }
        $auth->logout();
    }, 'http');

    $runner->add('Member portal upgrade routes load', function () use ($loginAs, $assertOk): void {
        $auth = $loginAs('lerato.pillay@example.co.za');
        $client = new HttpClient();
        foreach ([
            '/member',
            '/member/profile',
            '/member/change-requests',
            '/member/loans/apply',
            '/member/notifications',
        ] as $path) {
            $assertOk($client->get($path), 'Member ' . $path);
        }
        $auth->logout();
    }, 'http');

    $runner->add('Platform support roles users audit load', function () use ($assertOk): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $auth->login('admin@stockvel.local', 'Password123!');
        $client = new HttpClient();
        foreach ([
            '/platform/support',
            '/platform/roles',
            '/platform/users',
            '/platform/audit',
            '/platform/jobs',
            '/platform/profile',
            '/platform/security',
        ] as $path) {
            $assertOk($client->get($path), 'Platform ' . $path);
        }
        $auth->logout();
    }, 'http');

    $runner->add('Meeting show loads attendance without ma.status column', function () use ($loginAs, $assertOk): void {
        $auth = $loginAs('sipho.ndlovu@example.co.za');
        $meeting = test_db()->fetch(
            "SELECT m.id FROM meetings m
             INNER JOIN stokvels s ON s.id = m.stokvel_id
             WHERE s.slug = 'ubuntu-savings'
             ORDER BY m.id ASC LIMIT 1"
        );
        test_assert($meeting !== null, 'Seeded meeting required');
        $client = new HttpClient();
        $assertOk($client->get('/app/meetings/' . (int) $meeting['id']), 'Meeting show');
        $auth->logout();
    }, 'http');

    $runner->add('Member change request stores encrypted ID not plain', function () use ($loginAs): void {
        $auth = $loginAs('thabo.mokoena@example.co.za');
        $member = test_db()->fetch(
            "SELECT sm.id, sm.stokvel_id FROM memberships sm
             INNER JOIN users u ON u.id = sm.user_id
             INNER JOIN stokvels s ON s.id = sm.stokvel_id
             WHERE u.email = 'lerato.pillay@example.co.za' AND s.slug = 'ubuntu-savings'
             LIMIT 1"
        );
        test_assert($member !== null);
        /** @var MemberChangeRequestService $svc */
        $svc = App::getInstance()->container()->get(MemberChangeRequestService::class);
        $plain = '9001015800085';
        $id = $svc->submit(
            (int) $member['stokvel_id'],
            (int) $member['id'],
            (int) $auth->user()['id'],
            'profile_update',
            [
                'first_name' => 'Lerato',
                'last_name' => 'Pillay',
                'mobile' => '0820000001',
                'id_number' => $plain,
            ]
        );
        $row = test_db()->fetch('SELECT payload_json FROM member_change_requests WHERE id = :id', ['id' => $id]);
        test_assert($row !== null);
        $payload = json_decode((string) $row['payload_json'], true);
        test_assert(is_array($payload));
        test_assert(!isset($payload['id_number']) || $payload['id_number'] === '', 'Plain ID must not remain in payload');
        test_assert(!empty($payload['id_number_encrypted']), 'Encrypted ID required');
        test_assert(!str_contains((string) $row['payload_json'], $plain), 'Plain ID leaked into JSON');
        $decrypted = SensitiveData::decrypt((string) $payload['id_number_encrypted']);
        test_assert($decrypted === $plain, 'Encrypted ID must round-trip');
        $auth->logout();
    }, 'e2e');

    $runner->add('CSV contribution template downloads as text/csv', function () use ($loginAs): void {
        $auth = $loginAs('nomsa.dlamini@example.co.za');
        $client = new HttpClient();
        $res = $client->get('/app/contributions/import/template.csv');
        test_assert($res['status'] === 200, 'Template status ' . $res['status']);
        test_assert(
            str_contains($res['body'], 'Member Number or Email')
            && (str_contains($res['body'], 'Amount') || str_contains($res['body'], 'amount')),
            'Template missing expected columns'
        );
        $auth->logout();
    }, 'http');
};
