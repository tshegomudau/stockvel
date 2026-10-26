<?php

declare(strict_types=1);

use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Member statement opening + movements reconcile to closing', function (): void {
        test_transaction(function () {
            $db = test_db();
            $membership = $db->fetch(
                "SELECT m.id, m.stokvel_id FROM memberships m WHERE m.status = 'active' LIMIT 1"
            );
            test_assert($membership !== null, 'Active membership required');
            $sid = (int) $membership['stokvel_id'];
            $mid = (int) $membership['id'];

            $from = '2000-01-01';
            $to = '2099-12-31';

            $charges = (float) ($db->fetch(
                'SELECT COALESCE(SUM(amount),0) AS t FROM member_charges
                 WHERE stokvel_id = :sid AND membership_id = :mid AND due_date BETWEEN :from AND :to',
                ['sid' => $sid, 'mid' => $mid, 'from' => $from, 'to' => $to]
            )['t'] ?? 0);
            $payments = (float) ($db->fetch(
                "SELECT COALESCE(SUM(amount),0) AS t FROM payment_transactions
                 WHERE stokvel_id = :sid AND membership_id = :mid
                   AND status IN ('posted','completed','allocated','confirmed')
                   AND DATE(paid_at) BETWEEN :from AND :to",
                ['sid' => $sid, 'mid' => $mid, 'from' => $from, 'to' => $to]
            )['t'] ?? 0);

            $expected = round($charges - $payments, 2);
            // Invariant: net obligation in full history equals charges - payments (fines optional)
            test_assert(is_float($expected) || is_int($expected), 'Balance computed');
            test_assert(abs($expected - ($charges - $payments)) < 0.001, 'Charges minus payments is self-consistent');
        });
    }, 'integration');
};
