<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Exceptions\FinanceException;
use App\Services\Finance\FundService;
use App\Services\Finance\SavingGoalService;

final class GoalController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireViewAccess();
        $sid = $this->requireStokvel();
        $status = (string) $request->query('status', '');

        /** @var SavingGoalService $goals */
        $goals = app()->container()->get(SavingGoalService::class);

        return $this->render('stokvel/goals/index', [
            'title' => 'Saving goals',
            'activeNav' => 'goals',
            'navContext' => 'app',
            'goals' => $goals->list($sid, $status !== '' ? $status : null),
            'statusFilter' => $status,
            'canManage' => $this->canManage(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requireManageAccess();
        $sid = $this->requireStokvel();
        /** @var FundService $funds */
        $funds = app()->container()->get(FundService::class);
        $funds->ensureDefaults($sid);

        return $this->render('stokvel/goals/form', [
            'title' => 'New saving goal',
            'activeNav' => 'goals',
            'navContext' => 'app',
            'goal' => null,
            'funds' => $this->fundOptions($sid),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requireManageAccess();
        $sid = $this->requireStokvel();
        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'name' => 'required|min:2',
            'target_amount' => 'required',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Please check the goal details.');
            return $this->redirect('/app/goals/create');
        }

        try {
            /** @var SavingGoalService $goals */
            $goals = app()->container()->get(SavingGoalService::class);
            $id = $goals->create($sid, (int) $this->userId(), $data);
            Session::flash('success', 'Saving goal created. Progress reflects linked fund balance or recorded progress — not a bank balance.');
            return $this->redirect('/app/goals/' . $id);
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/goals/create');
        }
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireViewAccess();
        $sid = $this->requireStokvel();
        /** @var SavingGoalService $goals */
        $goals = app()->container()->get(SavingGoalService::class);
        $goal = $goals->find($sid, (int) $id);
        if ($goal === null) {
            Session::flash('error', 'Goal not found.');
            return $this->redirect('/app/goals');
        }

        return $this->render('stokvel/goals/show', [
            'title' => (string) $goal['name'],
            'activeNav' => 'goals',
            'navContext' => 'app',
            'goal' => $goal,
            'canManage' => $this->canManage(),
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requireManageAccess();
        $sid = $this->requireStokvel();
        /** @var SavingGoalService $goals */
        $goals = app()->container()->get(SavingGoalService::class);
        $goal = $goals->find($sid, (int) $id);
        if ($goal === null) {
            Session::flash('error', 'Goal not found.');
            return $this->redirect('/app/goals');
        }

        return $this->render('stokvel/goals/form', [
            'title' => 'Edit saving goal',
            'activeNav' => 'goals',
            'navContext' => 'app',
            'goal' => $goal,
            'funds' => $this->fundOptions($sid),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requireManageAccess();
        $sid = $this->requireStokvel();
        $data = $this->payload($request);
        $data['status'] = (string) $request->input('status', 'active');

        try {
            /** @var SavingGoalService $goals */
            $goals = app()->container()->get(SavingGoalService::class);
            $goals->update($sid, (int) $id, $data);
            Session::flash('success', 'Saving goal updated.');
            return $this->redirect('/app/goals/' . (int) $id);
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/goals/' . (int) $id . '/edit');
        }
    }

    public function updateStatus(Request $request, string $id): Response
    {
        $this->requireManageAccess();
        $sid = $this->requireStokvel();
        $status = trim((string) $request->input('status', ''));
        try {
            /** @var SavingGoalService $goals */
            $goals = app()->container()->get(SavingGoalService::class);
            if ($status === 'cancelled') {
                $goals->cancel($sid, (int) $id);
            } elseif ($status === 'achieved') {
                $goals->markAchieved($sid, (int) $id);
            } else {
                $goals->update($sid, (int) $id, ['status' => $status]);
            }
            Session::flash('success', 'Goal status updated.');
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect('/app/goals');
    }

    public function cancel(Request $request, string $id): Response
    {
        $this->requireManageAccess();
        $sid = $this->requireStokvel();
        try {
            /** @var SavingGoalService $goals */
            $goals = app()->container()->get(SavingGoalService::class);
            $goals->cancel($sid, (int) $id);
            Session::flash('success', 'Goal cancelled.');
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect('/app/goals');
    }

    private function requireViewAccess(): void
    {
        if ($this->tenant()->can('goals.view') || $this->canManage()) {
            return;
        }
        $this->requirePermission('goals.view');
    }

    private function requireManageAccess(): void
    {
        if ($this->canManage()) {
            return;
        }
        $this->requirePermission('goals.manage');
    }

    private function canManage(): bool
    {
        return $this->tenant()->can('goals.manage');
    }

    /** @return list<array<string, mixed>> */
    private function fundOptions(int $stokvelId): array
    {
        return $this->db()->fetchAll(
            'SELECT id, code, name FROM stokvel_funds WHERE stokvel_id = :sid AND is_active = 1 ORDER BY display_order ASC, name ASC',
            ['sid' => $stokvelId]
        );
    }

    /** @return array{name:string,description:?string,target_amount:string,target_date:?string,fund_id:?int,progress_amount:string} */
    private function payload(Request $request): array
    {
        $fundId = (int) $request->input('fund_id', 0);
        return [
            'name' => trim((string) $request->input('name', '')),
            'description' => trim((string) $request->input('description', '')),
            'target_amount' => (string) $request->input('target_amount', '0'),
            'target_date' => trim((string) $request->input('target_date', '')),
            'fund_id' => $fundId > 0 ? $fundId : null,
            'progress_amount' => (string) $request->input('progress_amount', '0'),
        ];
    }
}
