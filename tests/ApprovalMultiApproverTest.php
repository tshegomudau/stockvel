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
};
