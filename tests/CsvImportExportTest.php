<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Auth\AuthService;
use App\Services\Finance\CsvImportService;
use Tests\Support\HttpClient;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $loginTreasurer = static function (): AuthService {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login('nomsa.dlamini@example.co.za', 'Password123!');
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        test_assert($stokvel !== null);
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        return $auth;
    };

    $runner->add('CSV contribution template has friendly headers', function () use ($loginTreasurer): void {
        $auth = $loginTreasurer();
        $client = new HttpClient();
        foreach (['/app/contributions/import/template', '/app/contributions/import/template.csv'] as $path) {
            $res = $client->get($path);
            test_assert($res['status'] === 200, 'Template status ' . $res['status'] . ' for ' . $path);
            test_assert(str_contains($res['body'], 'Member Number or Email'), 'Friendly member header missing');
            test_assert(str_contains($res['body'], 'Payment Method'), 'Payment method header missing');
            test_assert(str_contains($res['body'], 'Payment Date'), 'Payment date header missing');
        }
        $auth->logout();
    }, 'http');

    $runner->add('CSV import preview reports row-level errors', function (): void {
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        test_assert($stokvel !== null);
        /** @var CsvImportService $csv */
        $csv = App::getInstance()->container()->get(CsvImportService::class);
        $content = "Member Number or Email,Amount,Payment Method,Payment Date,Reference\n"
            . ",100.00,eft,2026-01-01,\n"
            . "NO-SUCH-MEMBER,50.00,cash,2026-01-02,\n";
        $preview = $csv->preview((int) $stokvel['id'], $content);
        test_assert($preview['errors'] !== [], 'Expected validation errors');
        $joined = implode(' ', $preview['errors']);
        test_assert(str_contains($joined, 'Row 2'), 'Missing row 2 error');
        test_assert(str_contains($joined, 'Row 3') || str_contains($joined, 'not found'), 'Missing unknown member error');
    }, 'e2e');

    $runner->add('CSV import accepts valid seeded member row', function (): void {
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        test_assert($stokvel !== null);
        $member = test_db()->fetch(
            "SELECT sm.member_number FROM memberships sm
             INNER JOIN stokvels s ON s.id = sm.stokvel_id
             WHERE s.slug = 'ubuntu-savings' AND sm.status = 'active' AND sm.member_number IS NOT NULL
             LIMIT 1"
        );
        test_assert($member !== null && ($member['member_number'] ?? '') !== '');
        $treasurer = test_db()->fetch("SELECT id FROM users WHERE email = 'nomsa.dlamini@example.co.za' LIMIT 1");
        test_assert($treasurer !== null);

        /** @var CsvImportService $csv */
        $csv = App::getInstance()->container()->get(CsvImportService::class);
        $today = date('Y-m-d');
        $content = "Member Number or Email,Amount,Payment Method,Payment Date,Reference\n"
            . $member['member_number'] . ",25.00,cash,{$today},CSV-E2E-TEST\n";
        $result = $csv->import((int) $stokvel['id'], $content, (int) $treasurer['id'], 'csv-test-' . uniqid());
        test_assert($result['imported'] >= 1, 'Expected at least one imported payment');
    }, 'e2e');

    $runner->add('Report CSV export uses friendly headings without raw ids', function () use ($loginTreasurer): void {
        $auth = $loginTreasurer();
        $client = new HttpClient();
        foreach (['/app/reports/members/export', '/app/reports/members.csv'] as $path) {
            $res = $client->get($path);
            test_assert(in_array($res['status'], [200, 302], true), 'Export status ' . $res['status'] . ' for ' . $path);
            if ($res['status'] === 200) {
                test_assert(!preg_match('/(^|,)id(,|$)/i', explode("\n", $res['body'])[0] ?? ''), 'Export must not lead with id column');
                test_assert(
                    str_contains($res['body'], 'Member') || str_contains($res['body'], 'Email') || str_contains($res['body'], 'Message'),
                    'Export missing business columns'
                );
            }
        }
        $auth->logout();
    }, 'http');
};
