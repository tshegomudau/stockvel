<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Finance\AutoFineService;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Late contribution: no fine before grace; fine after; no duplicate', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null, 'Active stokvel required');
            $sid = (int) $stokvel['id'];
            $user = $db->fetch('SELECT id FROM users LIMIT 1');
            $uid = (int) $user['id'];
            $member = $db->fetch(
                "SELECT id FROM memberships WHERE stokvel_id = :sid AND status = 'active' LIMIT 1",
                ['sid' => $sid]
            );
            test_assert($member !== null, 'Membership required');
            $mid = (int) $member['id'];
            $now = now_utc();

            $db->exec(
                'UPDATE stokvel_settings SET grace_period_days = 7 WHERE stokvel_id = :sid',
                ['sid' => $sid]
            );

            $type = $db->fetch(
                "SELECT id FROM fine_types WHERE stokvel_id = :sid AND code = 'late_contribution' LIMIT 1",
                ['sid' => $sid]
            );
            if (!$type) {
                $db->insert(
                    "INSERT INTO fine_types
                        (stokvel_id, code, name, description, default_amount, is_automatic_eligible,
                         display_order, is_active, created_at, updated_at)
                     VALUES (?, 'late_contribution', 'Late contribution', 'Test', '50.00', 1, 1, 1, ?, ?)",
                    [$sid, $now, $now]
                );
            } else {
                $db->exec(
                    'UPDATE fine_types SET is_automatic_eligible = 1, is_active = 1 WHERE id = ? AND stokvel_id = ?',
                    [(int) $type['id'], $sid]
                );
            }

            /** @var AutoFineService $auto */
            $auto = App::getInstance()->container()->get(AutoFineService::class);

            // Within grace window (due yesterday, grace 7 days) — should NOT fine
            $withinId = (int) $db->insert(
                "INSERT INTO member_charges
                    (stokvel_id, membership_id, charge_code, description, amount, amount_paid, status, due_date, created_at, updated_at)
                 VALUES (?, ?, ?, 'Within grace', '100.00', '0.00', 'pending', DATE_SUB(UTC_DATE(), INTERVAL 1 DAY), ?, ?)",
                [$sid, $mid, 'grace-in-' . bin2hex(random_bytes(2)), $now, $now]
            );
            $previewIn = $auto->preview($sid, AutoFineService::RULE_LATE_CONTRIBUTION);
            foreach ($previewIn['candidates'] as $c) {
                test_assert((int) ($c['source_id'] ?? 0) !== $withinId, 'Charge still inside grace must not be fined');
            }

            // Past grace (due 30 days ago)
            $overId = (int) $db->insert(
                "INSERT INTO member_charges
                    (stokvel_id, membership_id, charge_code, description, amount, amount_paid, status, due_date, created_at, updated_at)
                 VALUES (?, ?, ?, 'Past grace', '100.00', '0.00', 'overdue', DATE_SUB(UTC_DATE(), INTERVAL 30 DAY), ?, ?)",
                [$sid, $mid, 'grace-out-' . bin2hex(random_bytes(2)), $now, $now]
            );
            $previewOut = $auto->preview($sid, AutoFineService::RULE_LATE_CONTRIBUTION);
            $hit = array_filter($previewOut['candidates'], static fn ($c) => (int) ($c['source_id'] ?? 0) === $overId);
            test_assert($hit !== [], 'Past-grace unpaid charge must appear in preview');

            $apply1 = $auto->apply($sid, AutoFineService::RULE_LATE_CONTRIBUTION, $uid);
            test_assert($apply1['issued'] >= 1, 'Apply must issue fine');
            $count1 = (int) ($db->fetch(
                'SELECT COUNT(*) AS c FROM fines WHERE stokvel_id = :sid AND idempotency_key = :k',
                ['sid' => $sid, 'k' => 'autofine:late_contrib:' . $overId]
            )['c'] ?? 0);
            test_assert($count1 === 1, 'Exactly one fine for overdue charge');

            $apply2 = $auto->apply($sid, AutoFineService::RULE_LATE_CONTRIBUTION, $uid);
            $count2 = (int) ($db->fetch(
                'SELECT COUNT(*) AS c FROM fines WHERE stokvel_id = :sid AND idempotency_key = :k',
                ['sid' => $sid, 'k' => 'autofine:late_contrib:' . $overId]
            )['c'] ?? 0);
            test_assert($count2 === 1, 'Re-apply must not duplicate');
            test_assert(is_array($apply2), 'Second apply returns result');

            // Fully paid — no fine
            $paidId = (int) $db->insert(
                "INSERT INTO member_charges
                    (stokvel_id, membership_id, charge_code, description, amount, amount_paid, status, due_date, created_at, updated_at)
                 VALUES (?, ?, ?, 'Paid old', '100.00', '100.00', 'paid', DATE_SUB(UTC_DATE(), INTERVAL 40 DAY), ?, ?)",
                [$sid, $mid, 'paid-old-' . bin2hex(random_bytes(2)), $now, $now]
            );
            $previewPaid = $auto->preview($sid, AutoFineService::RULE_LATE_CONTRIBUTION);
            foreach ($previewPaid['candidates'] as $c) {
                test_assert((int) ($c['source_id'] ?? 0) !== $paidId, 'Paid charge must not be auto-fined');
            }
        });
    }, 'integration');

    $runner->add('Missed meeting: present/apology skipped; absent fined once; unmarked meeting skipped', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            $sid = (int) $stokvel['id'];
            $user = $db->fetch('SELECT id FROM users LIMIT 1');
            $uid = (int) $user['id'];
            $members = $db->fetchAll(
                "SELECT id FROM memberships WHERE stokvel_id = :sid AND status = 'active' ORDER BY id ASC LIMIT 3",
                ['sid' => $sid]
            );
            test_assert(count($members) >= 2, 'Need at least 2 active members');
            $mPresent = (int) $members[0]['id'];
            $mAbsent = (int) $members[1]['id'];
            $mApology = count($members) > 2 ? (int) $members[2]['id'] : $mPresent;
            $now = now_utc();

            $type = $db->fetch(
                "SELECT id FROM fine_types WHERE stokvel_id = :sid AND code = 'missed_meeting' LIMIT 1",
                ['sid' => $sid]
            );
            if (!$type) {
                $db->insert(
                    "INSERT INTO fine_types
                        (stokvel_id, code, name, description, default_amount, is_automatic_eligible,
                         display_order, is_active, created_at, updated_at)
                     VALUES (?, 'missed_meeting', 'Missed meeting', 'Test', '50.00', 1, 2, 1, ?, ?)",
                    [$sid, $now, $now]
                );
            } else {
                $db->exec(
                    'UPDATE fine_types SET is_automatic_eligible = 1, is_active = 1 WHERE id = ? AND stokvel_id = ?',
                    [(int) $type['id'], $sid]
                );
            }

            $unrefined = (int) $db->insert(
                "INSERT INTO meetings
                    (stokvel_id, meeting_ref, title, meeting_type, scheduled_at, status, created_by_user_id, created_at, updated_at)
                 VALUES (?, ?, 'Unmarked attendance', 'regular', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 DAY), 'completed', ?, ?, ?)",
                [$sid, 'MTG-U-' . bin2hex(random_bytes(2)), $uid, $now, $now]
            );

            $meetingId = (int) $db->insert(
                "INSERT INTO meetings
                    (stokvel_id, meeting_ref, title, meeting_type, scheduled_at, status, created_by_user_id, created_at, updated_at)
                 VALUES (?, ?, 'Attendance finalised', 'regular', DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY), 'completed', ?, ?, ?)",
                [$sid, 'MTG-F-' . bin2hex(random_bytes(2)), $uid, $now, $now]
            );

            foreach ([
                [$mPresent, 'present'],
                [$mAbsent, 'absent'],
                [$mApology, 'apology'],
            ] as [$mid, $status]) {
                if ($mid === $mPresent && $status === 'apology' && $mApology === $mPresent) {
                    continue;
                }
                $db->insert(
                    'INSERT INTO meeting_attendance
                        (stokvel_id, meeting_id, membership_id, attendance_status, recorded_at, created_at)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$sid, $meetingId, $mid, $status, $now, $now]
                );
            }

            /** @var AutoFineService $auto */
            $auto = App::getInstance()->container()->get(AutoFineService::class);
            $preview = $auto->preview($sid, AutoFineService::RULE_MISSED_MEETING);

            foreach ($preview['candidates'] as $c) {
                test_assert((int) ($c['source_id'] ?? 0) !== $unrefined, 'Unmarked meeting must not fine');
                if ((int) ($c['source_id'] ?? 0) === $meetingId) {
                    test_assert((int) $c['membership_id'] !== $mPresent, 'Present member must not be fined');
                    if ($mApology !== $mPresent) {
                        test_assert((int) $c['membership_id'] !== $mApology, 'Apology must not be fined');
                    }
                }
            }

            $absentHits = array_filter(
                $preview['candidates'],
                static fn ($c) => (int) ($c['source_id'] ?? 0) === $meetingId && (int) ($c['membership_id'] ?? 0) === $mAbsent
            );
            test_assert($absentHits !== [], 'Absent member must appear in preview');

            $auto->apply($sid, AutoFineService::RULE_MISSED_MEETING, $uid);
            $key = 'autofine:absence:' . $meetingId . ':' . $mAbsent;
            $c1 = (int) ($db->fetch(
                'SELECT COUNT(*) AS c FROM fines WHERE stokvel_id = :sid AND idempotency_key = :k',
                ['sid' => $sid, 'k' => $key]
            )['c'] ?? 0);
            test_assert($c1 === 1, 'One absence fine');
            $auto->apply($sid, AutoFineService::RULE_MISSED_MEETING, $uid);
            $c2 = (int) ($db->fetch(
                'SELECT COUNT(*) AS c FROM fines WHERE stokvel_id = :sid AND idempotency_key = :k',
                ['sid' => $sid, 'k' => $key]
            )['c'] ?? 0);
            test_assert($c2 === 1, 'No duplicate absence fine');
        });
    }, 'integration');
};
