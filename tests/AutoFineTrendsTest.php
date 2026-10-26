<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Analytics\TrendsService;
use App\Services\Finance\AutoFineService;
use App\Services\Finance\FineService;
use App\Support\MoneyMath;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Auto-fine late contribution preview and idempotent apply', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null, 'Active stokvel required');
            $sid = (int) $stokvel['id'];
            $user = $db->fetch('SELECT id FROM users LIMIT 1');
            test_assert($user !== null, 'User required');
            $uid = (int) $user['id'];
            $member = $db->fetch(
                "SELECT id FROM memberships WHERE stokvel_id = :sid AND status = 'active' LIMIT 1",
                ['sid' => $sid]
            );
            test_assert($member !== null, 'Active membership required');
            $mid = (int) $member['id'];

            $now = now_utc();
            $exists = $db->fetch(
                "SELECT id FROM fine_types WHERE stokvel_id = :sid AND code = 'late_contribution' LIMIT 1",
                ['sid' => $sid]
            );
            if (!$exists) {
                $db->insert(
                    "INSERT INTO fine_types
                        (stokvel_id, code, name, description, default_amount, is_automatic_eligible,
                         display_order, is_active, created_at, updated_at)
                     VALUES (?, 'late_contribution', 'Late contribution', 'Test', '50.00', 1, 1, 1, ?, ?)",
                    [$sid, $now, $now]
                );
            } else {
                $db->exec(
                    'UPDATE fine_types SET is_automatic_eligible = 1, is_active = 1, default_amount = 50.00
                     WHERE id = ? AND stokvel_id = ?',
                    [(int) $exists['id'], $sid]
                );
            }

            $chargeId = (int) $db->insert(
                "INSERT INTO member_charges
                    (stokvel_id, membership_id, charge_code, description, amount, amount_paid, status, due_date, created_at, updated_at)
                 VALUES (?, ?, ?, ?, '100.00', '0.00', 'overdue', DATE_SUB(UTC_DATE(), INTERVAL 30 DAY), ?, ?)",
                [$sid, $mid, 'test-late-' . bin2hex(random_bytes(3)), 'Overdue test charge', $now, $now]
            );

            /** @var AutoFineService $auto */
            $auto = App::getInstance()->container()->get(AutoFineService::class);
            $preview = $auto->preview($sid, AutoFineService::RULE_LATE_CONTRIBUTION);
            test_assert($preview['enabled'] === true, 'Auto fine type should be enabled');
            $matched = array_filter(
                $preview['candidates'],
                static fn (array $c): bool => (int) ($c['source_id'] ?? 0) === $chargeId
            );
            test_assert($matched !== [], 'Preview should include overdue charge');

            $first = $auto->apply($sid, AutoFineService::RULE_LATE_CONTRIBUTION, $uid);
            test_assert($first['issued'] >= 1, 'Should issue at least one fine');
            $second = $auto->apply($sid, AutoFineService::RULE_LATE_CONTRIBUTION, $uid);
            $sameKeyCount = (int) ($db->fetch(
                "SELECT COUNT(*) AS c FROM fines WHERE stokvel_id = :sid AND idempotency_key = :k",
                ['sid' => $sid, 'k' => 'autofine:late_contrib:' . $chargeId]
            )['c'] ?? 0);
            test_assert($sameKeyCount === 1, 'Idempotent key must prevent duplicate late fines');
            test_assert($second['issued'] >= 0, 'Second apply returns without error');
        });
    }, 'integration');

    $runner->add('Annual management summary HTTP page uses year_end_month settings', function (): void {
        $auth = App::getInstance()->container()->get(\App\Services\Auth\AuthService::class);
        $auth->logout();
        $user = $auth->login('thabo.mokoena@example.co.za', 'Password123!');
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        test_assert($stokvel !== null);
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        $client = new \Tests\Support\HttpClient();
        $res = $client->get('/app/reports/annual');
        test_assert($res['status'] === 200, 'Annual report status ' . $res['status'] . ' ' . substr($res['body'], 0, 240));
        test_assert(
            str_contains($res['body'], 'Annual') || str_contains($res['body'], 'Management'),
            'Annual summary heading missing'
        );
        test_assert(
            !str_contains($res['body'], 'Unknown column') && !str_contains($res['body'], 'SQLSTATE'),
            'Annual page must not throw SQL errors'
        );
        $lower = strtolower($res['body']);
        test_assert(str_contains($lower, 'not an audited'), 'Must carry not-audited disclaimer');
        test_assert(str_contains($lower, 'management summary') || str_contains($lower, 'management report'), 'Must be labelled management summary');
        $auth->logout();
    }, 'http');

    $runner->add('Trends forecast uses trailing average of real collected amounts', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null, 'Active stokvel required');
            $sid = (int) $stokvel['id'];

            /** @var TrendsService $trends */
            $trends = App::getInstance()->container()->get(TrendsService::class);
            $data = $trends->monthlySeries($sid, 6);
            test_assert(isset($data['months']) && is_array($data['months']), 'Months series required');
            test_assert(count($data['months']) === 6, 'Expected 6 historical months');
            test_assert(str_contains((string) ($data['disclaimer'] ?? ''), 'projections'), 'Forecast disclaimer required');
            test_assert(count($data['forecast']) === 3, 'Expected 3 forecast months');
            foreach ($data['forecast'] as $f) {
                test_assert(isset($f['projected_collected']), 'Projected value required');
                test_assert(str_contains((string) ($f['method'] ?? ''), 'average'), 'Method must document trailing average');
            }

            $annual = $trends->annualSummary($sid, '2020-01-01', date('Y-m-d'));
            test_assert(isset($annual['total_contributions']), 'Annual contributions key');
            test_assert(str_contains((string) ($annual['disclaimer'] ?? ''), 'audited') === false
                || str_contains((string) ($annual['disclaimer'] ?? ''), 'Not an audited'), 'Must not claim audited status incorrectly');
            test_assert(str_contains((string) ($annual['disclaimer'] ?? ''), 'Management')
                || str_contains((string) ($annual['disclaimer'] ?? ''), 'not an audited'), 'Management disclaimer');
        });
    }, 'integration');

    $runner->add('Auto-fine missed meeting skips meetings without attendance marks', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null, 'Active stokvel required');
            $sid = (int) $stokvel['id'];
            $user = $db->fetch('SELECT id FROM users LIMIT 1');
            $uid = (int) $user['id'];
            $now = now_utc();

            $exists = $db->fetch(
                "SELECT id FROM fine_types WHERE stokvel_id = :sid AND code = 'missed_meeting' LIMIT 1",
                ['sid' => $sid]
            );
            if (!$exists) {
                $db->insert(
                    "INSERT INTO fine_types
                        (stokvel_id, code, name, description, default_amount, is_automatic_eligible,
                         display_order, is_active, created_at, updated_at)
                     VALUES (?, 'missed_meeting', 'Missed meeting', 'Test', '50.00', 1, 2, 1, ?, ?)",
                    [$sid, $now, $now]
                );
            }

            $meetingId = (int) $db->insert(
                "INSERT INTO meetings
                    (stokvel_id, meeting_ref, title, meeting_type, scheduled_at, status, created_by_user_id, created_at, updated_at)
                 VALUES (?, ?, 'No attendance meeting', 'regular', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY), 'completed', ?, ?, ?)",
                [$sid, 'MTG-AUTO-' . bin2hex(random_bytes(3)), $uid, $now, $now]
            );

            /** @var AutoFineService $auto */
            $auto = App::getInstance()->container()->get(AutoFineService::class);
            $preview = $auto->preview($sid, AutoFineService::RULE_MISSED_MEETING);
            foreach ($preview['candidates'] as $c) {
                test_assert((int) ($c['source_id'] ?? 0) !== $meetingId, 'Meeting without attendance must not produce candidates');
            }
        });
    }, 'integration');
};
