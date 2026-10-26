<?php

declare(strict_types=1);

use App\Core\App;
use App\Exceptions\FinanceException;
use App\Services\Finance\ChartOfAccountsService;
use App\Services\Loans\LoanService;
use App\Support\MoneyMath;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Loan lifecycle apply approve disburse repay settle with purpose', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
            test_assert($stokvel !== null);
            $sid = (int) $stokvel['id'];
            $user = $db->fetch('SELECT id FROM users LIMIT 1');
            $uid = (int) $user['id'];
            $member = $db->fetch(
                "SELECT id FROM memberships WHERE stokvel_id = :sid AND status = 'active' LIMIT 1",
                ['sid' => $sid]
            );
            test_assert($member !== null);
            $product = $db->fetch(
                "SELECT id, min_amount, max_amount, max_term_months FROM loan_products
                 WHERE stokvel_id = :sid AND is_active = 1 LIMIT 1",
                ['sid' => $sid]
            );
            test_assert($product !== null);
            $purpose = $db->fetch(
                "SELECT id FROM loan_purposes WHERE stokvel_id = :sid AND is_active = 1 LIMIT 1",
                ['sid' => $sid]
            );
            test_assert($purpose !== null, 'Loan purpose catalogue required');

            App::getInstance()->container()->get(ChartOfAccountsService::class)->ensureDefaultAccounts($sid);
            /** @var LoanService $loans */
            $loans = App::getInstance()->container()->get(LoanService::class);

            $amount = MoneyMath::normalize((string) ($product['min_amount'] ?? '1000'));
            $term = min(6, max(1, (int) ($product['max_term_months'] ?? 6)));

            $appId = $loans->apply(
                $sid,
                (int) $member['id'],
                (int) $product['id'],
                $amount,
                $term,
                $uid,
                '',
                null,
                (int) $purpose['id']
            );
            $stored = $db->fetch(
                'SELECT purpose, loan_purpose_id, status FROM loan_applications WHERE id = :id AND stokvel_id = :sid',
                ['id' => $appId, 'sid' => $sid]
            );
            test_assert((int) ($stored['loan_purpose_id'] ?? 0) === (int) $purpose['id']);
            test_assert((string) ($stored['status'] ?? '') === LoanService::STATUS_APPLIED);

            try {
                $loans->disburse($sid, $appId, $uid);
                test_assert(false, 'Disburse before approve must fail');
            } catch (FinanceException $e) {
                test_assert(str_contains(strtolower($e->getMessage()), 'committee'), $e->getMessage());
            }

            $loans->committeeApprove($sid, $appId, $uid);
            $loans->disburse($sid, $appId, $uid);

            try {
                $loans->disburse($sid, $appId, $uid);
                test_assert(false, 'Duplicate disburse must fail');
            } catch (FinanceException $e) {
                test_assert(str_contains(strtolower($e->getMessage()), 'already'), $e->getMessage());
            }

            $loan = $db->fetch(
                'SELECT id, outstanding_balance, status FROM loans WHERE loan_application_id = :aid AND stokvel_id = :sid LIMIT 1',
                ['aid' => $appId, 'sid' => $sid]
            );
            test_assert($loan !== null);
            $loanId = (int) $loan['id'];
            $outstanding = MoneyMath::normalize((string) ($loan['outstanding_balance'] ?? '0'));
            test_assert(MoneyMath::isPositive($outstanding), 'Disbursed loan must have outstanding balance');

            try {
                $loans->repay($sid, $loanId, '0', $uid);
                test_assert(false, 'Zero repayment must fail');
            } catch (FinanceException $e) {
                test_assert(str_contains(strtolower($e->getMessage()), 'positive'), $e->getMessage());
            }

            $partial = MoneyMath::div($outstanding, '2');
            $loans->repay($sid, $loanId, $partial, $uid, null, 'eft');
            $afterPartial = $db->fetch(
                'SELECT outstanding_balance, status FROM loans WHERE id = :id AND stokvel_id = :sid',
                ['id' => $loanId, 'sid' => $sid]
            );
            test_assert(
                MoneyMath::comp((string) ($afterPartial['outstanding_balance'] ?? '0'), $outstanding) < 0,
                'Partial repayment must reduce outstanding'
            );

            $settle = $loans->earlySettle($sid, $loanId, $uid);
            test_assert(isset($settle['outstanding']) || isset($settle['amount']) || is_array($settle));
            $final = $db->fetch(
                'SELECT outstanding_balance, status FROM loans WHERE id = :id AND stokvel_id = :sid',
                ['id' => $loanId, 'sid' => $sid]
            );
            test_assert(
                MoneyMath::comp((string) ($final['outstanding_balance'] ?? '0'), '0') === 0
                || in_array((string) ($final['status'] ?? ''), ['settled', 'closed', 'paid'], true),
                'Loan should be settled after earlySettle'
            );
        });
    }, 'integration');
};
