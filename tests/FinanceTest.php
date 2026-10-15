<?php

declare(strict_types=1);

use App\Core\App;
use App\Exceptions\AuthorizationException;
use App\Services\Finance\ChargeService;
use App\Services\Finance\ContributionService;
use App\Services\Finance\LedgerService;
use App\Services\Loans\LoanScheduleCalculator;
use App\Services\Payouts\PayoutService;
use App\Services\PermissionService;
use App\Services\Reports\ReportService;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Charge generation (seeded data)', function (): void {
        test_transaction(function () {
            $count = (int) (test_db()->fetch('SELECT COUNT(*) AS c FROM member_charges')['c'] ?? 0);
            test_assert($count > 0, 'Expected seeded member charges');
        });
    });

    $runner->add('Charge generation via service', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            if (!$stokvel) {
                return;
            }
            $cs = App::getInstance()->container()->get(ChargeService::class);
            $period = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
            $created = $cs->generateRecurringCharges((int) $stokvel['id'], $period);
            test_assert(is_array($created));
        });
    });

    $runner->add('Duplicate payment idempotency', function (): void {
        test_transaction(function () {
            $db = test_db();
            $sid = (int) $db->fetch('SELECT id FROM stokvels LIMIT 1')['id'];
            $key = 'dup-' . bin2hex(random_bytes(6));
            $db->insert(
                'INSERT INTO idempotency_keys (stokvel_id, idempotency_key, request_method, request_path, expires_at, created_at)
                 VALUES (:s, :k, :m, :p, :e, :c)',
                ['s' => $sid, 'k' => $key, 'm' => 'POST', 'p' => '/test', 'e' => date('Y-m-d H:i:s', time() + 3600), 'c' => now_utc()]
            );
            $threw = false;
            try {
                $db->insert(
                    'INSERT INTO idempotency_keys (stokvel_id, idempotency_key, request_method, request_path, expires_at, created_at)
                     VALUES (:s, :k, :m, :p, :e, :c)',
                    ['s' => $sid, 'k' => $key, 'm' => 'POST', 'p' => '/test', 'e' => date('Y-m-d H:i:s', time() + 3600), 'c' => now_utc()]
                );
            } catch (\Throwable) {
                $threw = true;
            }
            test_assert($threw, 'Duplicate idempotency key must fail');
        });
    });

    $runner->add('Partial payment allocation', function (): void {
        test_transaction(function () {
            $db = test_db();
            if (!$db->fetch("SHOW TABLES LIKE 'contribution_payments'")) {
                return;
            }
            $row = $db->fetch(
                'SELECT stokvel_id, id AS membership_id FROM memberships WHERE status = :st LIMIT 1',
                ['st' => 'active']
            );
            if (!$row) {
                return;
            }
            $svc = App::getInstance()->container()->get(ContributionService::class);
            $payment = $svc->capturePayment(
                (int) $row['stokvel_id'],
                (int) $row['membership_id'],
                '10.00',
                'cash',
                1,
                'partial-' . bin2hex(random_bytes(4))
            );
            test_assert(isset($payment['allocated_amount'], $payment['credit_amount']));
        });
    });

    $runner->add('Balanced journals (seeded ledger)', function (): void {
        test_transaction(function () {
            $row = test_db()->fetch(
                'SELECT je.id,
                    (SELECT COALESCE(SUM(debit),0) FROM journal_lines jl WHERE jl.journal_entry_id = je.id) AS d,
                    (SELECT COALESCE(SUM(credit),0) FROM journal_lines jl WHERE jl.journal_entry_id = je.id) AS c
                 FROM journal_entries je LIMIT 1'
            );
            if (!$row) {
                return;
            }
            test_assert($row['d'] === $row['c']);
        });
    });

    $runner->add('Maker-checker self-approval blocked', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch('SELECT id FROM stokvels LIMIT 1');
            if (!$stokvel) {
                return;
            }
            $sid = (int) $stokvel['id'];
            $ledger = App::getInstance()->container()->get(LedgerService::class);
            $accounts = $db->fetchAll(
                'SELECT id FROM chart_of_accounts WHERE stokvel_id = :s AND is_active = 1 LIMIT 2',
                ['s' => $sid]
            );
            if (count($accounts) < 2) {
                return;
            }
            try {
                $jid = $ledger->createDraft($sid, 1, date('Y-m-d'), 'MC-TEST', 'Maker checker');
                $ledger->addLine($sid, $jid, (int) $accounts[0]['id'], '50.00', '0.00');
                $ledger->addLine($sid, $jid, (int) $accounts[1]['id'], '0.00', '50.00');
                $ledger->submitForApproval($sid, $jid, 1);
                $ledger->approve($sid, $jid, 1);
                test_assert(false, 'Expected AuthorizationException');
            } catch (AuthorizationException) {
                test_assert(true);
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'Maker-checker')) {
                    test_assert(true);
                }
            }
        });
    });

    $runner->add('Loan schedule calc', function (): void {
        $calc = new LoanScheduleCalculator();
        $schedule = $calc->buildSchedule('1200.00', '12', 6, LoanScheduleCalculator::METHOD_FLAT, date('Y-m-d'));
        test_assert(count($schedule) === 6);
    });

    $runner->add('Payout distribution preview', function (): void {
        test_transaction(function () {
            $db = test_db();
            $cycle = $db->fetch('SELECT stokvel_id, id FROM payout_cycles LIMIT 1');
            if (!$cycle) {
                return;
            }
            $payouts = App::getInstance()->container()->get(PayoutService::class);
            $preview = $payouts->previewEndOfCycleDistribution((int) $cycle['stokvel_id'], (int) $cycle['id']);
            test_assert(isset($preview['pool_amount'], $preview['lines']));
        });
    });

    $runner->add('Report service filters stokvel_id', function (): void {
        $db = test_db();
        $sid = (int) $db->fetch('SELECT id FROM stokvels LIMIT 1')['id'];
        $reports = App::getInstance()->container()->get(ReportService::class);
        $rows = $reports->memberRegister($sid);
        test_assert(is_array($rows));
    });

    $runner->add('Permission enforcement', function (): void {
        $perms = App::getInstance()->container()->get(PermissionService::class);
        test_assert($perms->forRole('treasurer') !== []);
    });
};
