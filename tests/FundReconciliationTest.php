<?php

declare(strict_types=1);

use App\Core\App;
use App\Exceptions\FinanceException;
use App\Services\Finance\FundService;
use App\Support\MoneyMath;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Fund balance equals credits minus debits', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null, 'Active stokvel required');
            $sid = (int) $stokvel['id'];
            $user = $db->fetch('SELECT id FROM users LIMIT 1');
            test_assert($user !== null, 'User required');
            $uid = (int) $user['id'];

            /** @var FundService $funds */
            $funds = App::getInstance()->container()->get(FundService::class);
            $funds->ensureDefaults($sid);
            $admin = $funds->findAdminFund($sid);
            test_assert($admin !== null, 'Admin fund required');
            $fid = (int) $admin['id'];

            $key = 'fund-rec-' . bin2hex(random_bytes(4));
            $funds->credit($sid, $fid, '100.00', $uid, [
                'description' => 'Test credit',
                'idempotency_key' => $key . '-c1',
            ]);
            $funds->credit($sid, $fid, '50.00', $uid, [
                'description' => 'Test credit 2',
                'idempotency_key' => $key . '-c2',
            ]);
            $funds->debit($sid, $fid, '30.00', $uid, [
                'description' => 'Test debit',
                'idempotency_key' => $key . '-d1',
            ]);

            $summary = $funds->summary($sid, $fid);
            $expected = MoneyMath::sub($summary['collected'], $summary['spent']);
            test_assert(
                MoneyMath::equals($summary['balance'], $expected),
                'Balance must equal credits - debits; got ' . $summary['balance'] . ' vs ' . $expected
            );
            test_assert(
                MoneyMath::equals($summary['balance'], '120.00'),
                'Expected recorded balance 120.00, got ' . $summary['balance']
            );
        });
    });

    $runner->add('Fund debit blocked when it would go negative', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null, 'Active stokvel required');
            $sid = (int) $stokvel['id'];
            $user = $db->fetch('SELECT id FROM users LIMIT 1');
            $uid = (int) $user['id'];

            /** @var FundService $funds */
            $funds = App::getInstance()->container()->get(FundService::class);
            $funds->ensureDefaults($sid);
            $admin = $funds->findAdminFund($sid);
            $fid = (int) $admin['id'];

            // Isolate with a fresh fund that starts at zero.
            $now = now_utc();
            $code = 'test_neg_' . substr(bin2hex(random_bytes(3)), 0, 6);
            $newId = (int) $db->insert(
                'INSERT INTO stokvel_funds
                    (stokvel_id, code, name, description, is_system, allow_negative, display_order, is_active, created_at, updated_at)
                 VALUES
                    (:sid, :code, :name, :desc, 0, 0, 99, 1, :ca, :ua)',
                [
                    'sid' => $sid,
                    'code' => $code,
                    'name' => 'Neg block test',
                    'desc' => 'Temporary',
                    'ca' => $now,
                    'ua' => $now,
                ]
            );

            $funds->credit($sid, $newId, '10.00', $uid, ['description' => 'Seed']);
            test_assert(MoneyMath::equals($funds->balance($sid, $newId), '10.00'), 'Seed credit applied');

            // Nested FundService::transaction() rollBack() resets the outer test transaction.
            // Assert the block via exception only; balance math is covered by the prior test.
            $threw = false;
            $message = '';
            try {
                $funds->debit($sid, $newId, '25.00', $uid, ['description' => 'Overdraw']);
            } catch (FinanceException $e) {
                $threw = true;
                $message = strtolower($e->getMessage());
            }
            test_assert($threw, 'Debit that would go negative must be blocked');
            test_assert(
                str_contains($message, 'insufficient') || str_contains($message, 'negative'),
                'Expected insufficient/negative message, got: ' . $message
            );
        });
    });
};
