<?php

declare(strict_types=1);

use App\Core\App;
use App\Exceptions\FinanceException;
use App\Services\Approvals\ApprovalService;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Multi-approval stays pending until threshold met', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null, 'Active stokvel required');
            $sid = (int) $stokvel['id'];

            $users = $db->fetchAll('SELECT id FROM users ORDER BY id ASC LIMIT 3');
            test_assert(count($users) >= 3, 'Need at least 3 users');
            $requester = (int) $users[0]['id'];
            $approver1 = (int) $users[1]['id'];
            $approver2 = (int) $users[2]['id'];

            /** @var ApprovalService $approvals */
            $approvals = App::getInstance()->container()->get(ApprovalService::class);
            $approvals->upsertPolicy($sid, 'test_expense', 2, null, []);

            $refId = random_int(100000, 999999);
            $requestId = $approvals->createRequest($sid, 'test_expense', 'test_expense', $refId, $requester, '500.00');

            $row = $db->fetch('SELECT * FROM approval_requests WHERE id = :id AND stokvel_id = :sid', ['id' => $requestId, 'sid' => $sid]);
            test_assert($row !== null);
            test_assert((string) ($row['status'] ?? '') === 'pending');
            test_assert((int) ($row['required_approvals'] ?? 0) === 2);

            $r1 = $approvals->approve($sid, $requestId, $approver1, 'First yes');
            test_assert($r1['finalised'] === false, 'Should stay pending after first approval');
            test_assert($r1['status'] === 'pending');
            test_assert($r1['approval_count'] === 1);

            $row = $db->fetch('SELECT status, approval_count FROM approval_requests WHERE id = :id', ['id' => $requestId]);
            test_assert(($row['status'] ?? '') === 'pending');
            test_assert((int) ($row['approval_count'] ?? 0) === 1);

            $r2 = $approvals->approve($sid, $requestId, $approver2, 'Second yes');
            test_assert($r2['finalised'] === true);
            test_assert($r2['status'] === 'approved');

            $row = $db->fetch('SELECT status, approval_count FROM approval_requests WHERE id = :id', ['id' => $requestId]);
            test_assert(($row['status'] ?? '') === 'approved');
            test_assert((int) ($row['approval_count'] ?? 0) === 2);

            $decisions = $approvals->decisionsFor($sid, $requestId);
            test_assert(count($decisions) === 2);
        });
    }, 'unit');

    $runner->add('Rejection requires reason and finalises', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            $sid = (int) $stokvel['id'];
            $users = $db->fetchAll('SELECT id FROM users ORDER BY id ASC LIMIT 2');
            $requester = (int) $users[0]['id'];
            $rejector = (int) $users[1]['id'];

            /** @var ApprovalService $approvals */
            $approvals = App::getInstance()->container()->get(ApprovalService::class);
            $requestId = $approvals->createRequest($sid, 'burial_claim', 'burial_claim', random_int(100000, 999999), $requester, '100.00');

            $threw = false;
            try {
                $approvals->reject($sid, $requestId, $rejector, '   ');
            } catch (FinanceException $e) {
                $threw = true;
                test_assert(str_contains(strtolower($e->getMessage()), 'reason'));
            }
            test_assert($threw, 'Empty rejection reason must fail');

            $result = $approvals->reject($sid, $requestId, $rejector, 'Insufficient documentation');
            test_assert($result['finalised'] === true);
            test_assert($result['status'] === 'rejected');

            $row = $db->fetch('SELECT status, decision_notes FROM approval_requests WHERE id = :id', ['id' => $requestId]);
            test_assert(($row['status'] ?? '') === 'rejected');
            test_assert(($row['decision_notes'] ?? '') === 'Insufficient documentation');
        });
    }, 'unit');

    $runner->add('Single eligible admin caps required approvals to one', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null);
            $sid = (int) $stokvel['id'];

            /** @var ApprovalService $approvals */
            $approvals = App::getInstance()->container()->get(ApprovalService::class);
            $approvals->upsertPolicy($sid, 'test_solo_admin', 3, null, []);

            // Pick an active chairperson/treasurer as the only "pool" actor for this assertion path.
            $admin = $db->fetch(
                "SELECT m.user_id
                 FROM memberships m
                 INNER JOIN membership_roles mr ON mr.membership_id = m.id AND mr.stokvel_id = m.stokvel_id
                 INNER JOIN roles r ON r.id = mr.role_id
                 WHERE m.stokvel_id = :sid AND m.status = 'active'
                   AND r.code IN ('chairperson', 'treasurer', 'secretary')
                 ORDER BY m.id ASC LIMIT 1",
                ['sid' => $sid]
            );
            test_assert($admin !== null, 'Need an admin membership on seeded stokvel');
            $adminId = (int) $admin['user_id'];

            // Requester is a different user so the admin is the only eligible non-requester.
            $requester = $db->fetch(
                'SELECT id FROM users WHERE id <> :uid ORDER BY id ASC LIMIT 1',
                ['uid' => $adminId]
            );
            test_assert($requester !== null);
            $requesterId = (int) $requester['id'];

            $effective = $approvals->effectiveRequiredApprovals($sid, $requesterId, 3);
            test_assert($effective >= 1, 'Effective required must be at least 1');

            $requestId = $approvals->createRequest(
                $sid,
                'test_solo_admin',
                'test_solo_admin',
                random_int(100000, 999999),
                $requesterId,
                '50.00'
            );
            $row = $db->fetch('SELECT required_approvals FROM approval_requests WHERE id = :id', ['id' => $requestId]);
            $required = (int) ($row['required_approvals'] ?? 0);
            $pool = $approvals->countEligibleApprovers($sid, $requesterId);
            test_assert(
                $required === max(1, min(3, max(1, $pool))),
                "Required {$required} should be capped to eligible pool {$pool}"
            );

            // If pool is 1, first approval must finalise.
            if ($pool === 1) {
                $result = $approvals->approve($sid, $requestId, $adminId, 'Solo admin ok');
                test_assert($result['finalised'] === true, 'Single eligible admin must finalise');
                test_assert($result['status'] === 'approved');
            }
        });
    }, 'unit');

    $runner->add('Amount threshold selects higher min_approvals', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            $sid = (int) $stokvel['id'];

            /** @var ApprovalService $approvals */
            $approvals = App::getInstance()->container()->get(ApprovalService::class);
            $approvals->upsertPolicy($sid, 'loan_application', 1, null, []);
            $approvals->upsertPolicy($sid, 'loan_application', 3, '10000.00', []);

            $low = $approvals->resolvePolicy($sid, 'loan_application', '500.00');
            test_assert($low['min_approvals'] === 1, 'Below threshold expects 1, got ' . $low['min_approvals']);

            $high = $approvals->resolvePolicy($sid, 'loan_application', '15000.00');
            test_assert($high['min_approvals'] === 3, 'Above threshold expects 3, got ' . $high['min_approvals']);
        });
    }, 'unit');

    $runner->add('Contribution edit request type uses thresholds and applies on approval', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1")
                ?: $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null, 'Active stokvel required');
            $sid = (int) $stokvel['id'];

            $membership = $db->fetch(
                "SELECT id FROM memberships WHERE stokvel_id = :sid AND status = 'active' ORDER BY id ASC LIMIT 1",
                ['sid' => $sid]
            );
            test_assert($membership !== null, 'Active membership required');
            $memberId = (int) $membership['id'];

            $users = $db->fetchAll('SELECT id FROM users ORDER BY id ASC LIMIT 3');
            test_assert(count($users) >= 2, 'Need requester and approver');
            $requester = (int) $users[0]['id'];
            $approver = (int) $users[1]['id'];

            /** @var \App\Services\Finance\ChartOfAccountsService $coa */
            $coa = App::getInstance()->container()->get(\App\Services\Finance\ChartOfAccountsService::class);
            $coa->ensureDefaultAccounts($sid);

            /** @var \App\Services\Finance\ContributionService $contributions */
            $contributions = App::getInstance()->container()->get(\App\Services\Finance\ContributionService::class);
            $payment = $contributions->capturePayment($sid, $memberId, '250.00', 'eft', $requester, null, 'EDIT-REF');
            $paymentId = (int) $payment['id'];

            /** @var ApprovalService $approvals */
            $approvals = App::getInstance()->container()->get(ApprovalService::class);
            $approvals->upsertPolicy($sid, 'contribution_edit', 1, null, []);

            /** @var \App\Services\Finance\ContributionEditRequestService $edits */
            $edits = App::getInstance()->container()->get(\App\Services\Finance\ContributionEditRequestService::class);
            $submitted = $edits->submit($sid, $paymentId, $requester, [
                'membership_id' => $memberId,
                'amount' => '300.00',
                'payment_method' => 'cash',
                'paid_at' => date('Y-m-d'),
                'external_reference' => 'EDIT-REF-FIXED',
                'edit_reason' => 'Corrected amount after bank statement',
            ]);
            test_assert(($submitted['edit_request_id'] ?? 0) > 0);
            test_assert(($submitted['approval_request_id'] ?? 0) > 0);

            $before = $db->fetch('SELECT amount, payment_method, external_reference FROM payment_transactions WHERE id = :id', ['id' => $paymentId]);
            test_assert(\App\Support\MoneyMath::equals((string) $before['amount'], '250.00'), 'Payment must stay unchanged while pending');
            test_assert(($before['payment_method'] ?? '') === 'eft');

            $approvalRow = $db->fetch(
                'SELECT * FROM approval_requests WHERE id = :id AND stokvel_id = :sid',
                ['id' => $submitted['approval_request_id'], 'sid' => $sid]
            );
            test_assert(($approvalRow['request_type'] ?? '') === 'contribution_edit');
            test_assert(($approvalRow['reference_type'] ?? '') === 'contribution_edit_request');
            test_assert(\App\Support\MoneyMath::equals((string) ($approvalRow['amount'] ?? '0'), '300.00'));

            $result = $approvals->approve($sid, (int) $submitted['approval_request_id'], $approver, 'Looks correct');
            test_assert($result['finalised'] === true, 'Single-threshold contribution edit should finalise');
            test_assert($result['status'] === 'approved');

            $after = $db->fetch('SELECT amount, payment_method, external_reference FROM payment_transactions WHERE id = :id', ['id' => $paymentId]);
            test_assert(\App\Support\MoneyMath::equals((string) $after['amount'], '300.00'), 'Approved edit should update amount');
            test_assert(($after['payment_method'] ?? '') === 'cash');
            test_assert(($after['external_reference'] ?? '') === 'EDIT-REF-FIXED');

            $editRow = $db->fetch(
                'SELECT status FROM contribution_edit_requests WHERE id = :id',
                ['id' => $submitted['edit_request_id']]
            );
            test_assert(($editRow['status'] ?? '') === 'approved');
        });
    }, 'unit');
};
