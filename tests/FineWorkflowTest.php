<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Finance\FineService;
use App\Support\MoneyMath;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Fine issue and waive workflow', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null, 'Active stokvel required');
            $sid = (int) $stokvel['id'];

            $member = $db->fetch(
                "SELECT id FROM memberships WHERE stokvel_id = :sid AND status = 'active' LIMIT 1",
                ['sid' => $sid]
            );
            test_assert($member !== null, 'Active membership required');
            $mid = (int) $member['id'];

            $user = $db->fetch('SELECT id FROM users LIMIT 1');
            $uid = (int) $user['id'];

            $now = now_utc();
            $code = 'late_' . substr(bin2hex(random_bytes(3)), 0, 6);
            $typeId = (int) $db->insert(
                'INSERT INTO fine_types
                    (stokvel_id, code, name, description, default_amount, display_order, is_active, created_at, updated_at)
                 VALUES
                    (:sid, :code, :name, :desc, :amt, 1, 1, :ca, :ua)',
                [
                    'sid' => $sid,
                    'code' => $code,
                    'name' => 'Late to meeting',
                    'desc' => 'Test fine type',
                    'amt' => '50.00',
                    'ca' => $now,
                    'ua' => $now,
                ]
            );

            /** @var FineService $fines */
            $fines = App::getInstance()->container()->get(FineService::class);
            $ikey = 'fine-wf-' . bin2hex(random_bytes(4));
            $fineId = $fines->issue($sid, $mid, $typeId, '50.00', $uid, [
                'reason' => 'Arrived 20 minutes late',
                'idempotency_key' => $ikey,
            ]);
            test_assert($fineId > 0, 'Fine id expected');

            $dup = $fines->issue($sid, $mid, $typeId, '50.00', $uid, [
                'reason' => 'Should not create again',
                'idempotency_key' => $ikey,
            ]);
            test_assert($dup === $fineId, 'Idempotent issue must return same fine id');

            $row = $db->fetch('SELECT * FROM fines WHERE id = :id LIMIT 1', ['id' => $fineId]);
            test_assert($row !== null, 'Fine row missing');
            test_assert((string) $row['status'] === 'outstanding', 'Expected outstanding');
            test_assert((string) $row['fine_type_name_snapshot'] === 'Late to meeting', 'Snapshot name required');
            test_assert(MoneyMath::equals((string) $row['amount'], '50.00'), 'Amount snapshot');

            $fines->waive($sid, $fineId, $uid, 'Committee waived first offence');
            $after = $db->fetch('SELECT * FROM fines WHERE id = :id LIMIT 1', ['id' => $fineId]);
            test_assert((string) $after['status'] === 'waived', 'Expected waived status');
            test_assert(MoneyMath::equals((string) $after['waived_amount'], '50.00'), 'Full waive amount');

            $list = $fines->listForMember($sid, $mid);
            $found = false;
            foreach ($list as $item) {
                if ((int) $item['id'] === $fineId) {
                    $found = true;
                    break;
                }
            }
            test_assert($found, 'Issued fine must appear in member list');
        });
    });
};
