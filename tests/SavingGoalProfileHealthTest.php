<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Analytics\GroupHealthService;
use App\Services\Finance\FundService;
use App\Services\Finance\SavingGoalService;
use App\Services\Members\ProfileCompletenessService;
use App\Support\MoneyMath;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Saving goal prefers fund balance for progress', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null);
            $sid = (int) $stokvel['id'];
            $uid = (int) ($db->fetch('SELECT id FROM users LIMIT 1')['id'] ?? 0);

            /** @var FundService $funds */
            $funds = App::getInstance()->container()->get(FundService::class);
            $funds->ensureDefaults($sid);
            $now = now_utc();
            $code = 'goal_' . substr(bin2hex(random_bytes(3)), 0, 6);
            $fundId = (int) $db->insert(
                'INSERT INTO stokvel_funds
                    (stokvel_id, code, name, description, is_system, allow_negative, display_order, is_active, created_at, updated_at)
                 VALUES (:sid, :code, :name, NULL, 0, 0, 50, 1, :ca, :ua)',
                ['sid' => $sid, 'code' => $code, 'name' => 'Goal fund', 'ca' => $now, 'ua' => $now]
            );
            $funds->credit($sid, $fundId, '250.00', $uid, [
                'description' => 'Goal progress credit',
                'idempotency_key' => 'goal-prog-' . $code,
            ]);

            /** @var SavingGoalService $goals */
            $goals = App::getInstance()->container()->get(SavingGoalService::class);
            $goalId = $goals->create($sid, $uid, [
                'name' => 'Emergency reserve',
                'target_amount' => '1000.00',
                'fund_id' => $fundId,
                'progress_amount' => '10.00',
            ]);
            $goal = $goals->find($sid, $goalId);
            test_assert($goal !== null);
            test_assert(($goal['progress_source'] ?? '') === 'fund');
            test_assert(MoneyMath::equals((string) $goal['current_progress'], '250.00'), 'Expected fund balance 250, got ' . ($goal['current_progress'] ?? ''));
            test_assert((int) ($goal['progress_percent'] ?? 0) === 25);
        });
    }, 'unit');

    $runner->add('Profile completeness reports percent and missing checklist', function (): void {
        test_transaction(function () {
            $db = test_db();
            $member = $db->fetch(
                "SELECT m.id, m.stokvel_id FROM memberships m WHERE m.status = 'active' LIMIT 1"
            );
            test_assert($member !== null);
            /** @var ProfileCompletenessService $profiler */
            $profiler = App::getInstance()->container()->get(ProfileCompletenessService::class);
            $result = $profiler->forMembership((int) $member['stokvel_id'], (int) $member['id']);
            test_assert($result['total'] >= 5);
            test_assert($result['percent'] >= 0 && $result['percent'] <= 100);
            test_assert(is_array($result['factors']));
            test_assert(is_array($result['missing']));
            test_assert($result['complete'] + count($result['missing']) === $result['total']);
        });
    }, 'unit');

    $runner->add('Group health score uses real components without claiming credit', function (): void {
        test_transaction(function () {
            $db = test_db();
            $stokvel = $db->fetch("SELECT id FROM stokvels WHERE status = 'active' LIMIT 1");
            test_assert($stokvel !== null);
            $sid = (int) $stokvel['id'];

            /** @var GroupHealthService $health */
            $health = App::getInstance()->container()->get(GroupHealthService::class);
            $report = $health->score($sid);

            test_assert(isset($report['score'], $report['band'], $report['components'], $report['disclaimer']));
            test_assert($report['score'] >= 0 && $report['score'] <= 100);
            test_assert(!str_contains(strtolower($report['disclaimer']), 'credit rating') || str_contains(strtolower($report['disclaimer']), 'not a credit'));
            test_assert(isset($report['components']['contribution_collection']));
            test_assert(isset($report['components']['arrears_pressure']));
            test_assert(is_array($report['recommendations']));
            test_assert($report['recommendations'] !== []);
        });
    }, 'unit');
};
