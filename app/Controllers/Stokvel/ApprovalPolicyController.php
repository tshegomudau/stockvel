<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\FinanceException;
use App\Services\Approvals\ApprovalService;

final class ApprovalPolicyController extends Controller
{
    /** @var list<string> */
    private const ALLOWED_ROLE_CODES = [
        'chairperson',
        'treasurer',
        'secretary',
        'committee_member',
        'ordinary_member',
        'auditor',
    ];

    public function index(Request $request): Response
    {
        $this->requirePolicyAccess();
        $sid = $this->requireStokvel();

        /** @var ApprovalService $approvals */
        $approvals = app()->container()->get(ApprovalService::class);
        $settings = $this->db()->fetch(
            'SELECT default_min_approvals, require_dual_approval FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $sid]
        ) ?? [];

        return $this->render('stokvel/approvals/policies', [
            'title' => 'Approval thresholds',
            'activeNav' => 'approvals',
            'navContext' => 'app',
            'policies' => $approvals->listPolicies($sid),
            'approvalRequestTypes' => ApprovalService::REQUEST_TYPE_OPTIONS,
            'roleOptions' => $this->roleOptions(),
            'settings' => $settings,
            'editPolicy' => null,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePolicyAccess();
        $sid = $this->requireStokvel();

        if ($request->input('default_min_approvals') !== null && !$request->input('create_policy')) {
            $defaultMin = max(1, (int) $request->input('default_min_approvals', 1));
            $this->saveDefaultMinApprovals($sid, $defaultMin);
            Session::flash('success', 'Default minimum approvals saved.');
            return $this->redirect('/app/approvals/policies');
        }

        [$type, $min, $threshold, $roles] = $this->parsePolicyInput($request);
        if ($min < 1) {
            Session::flash('error', 'Minimum approvals must be at least 1.');
            return $this->redirect('/app/approvals/policies');
        }

        try {
            /** @var ApprovalService $approvals */
            $approvals = app()->container()->get(ApprovalService::class);
            $approvals->upsertPolicy($sid, $type, $min, $threshold, $roles);
            Session::flash('success', 'Approval policy saved.');
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
        } catch (\Throwable) {
            Session::flash('error', 'Could not save approval policy.');
        }

        return $this->redirect('/app/approvals/policies');
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePolicyAccess();
        $sid = $this->requireStokvel();

        /** @var ApprovalService $approvals */
        $approvals = app()->container()->get(ApprovalService::class);
        $policy = $approvals->findPolicy($sid, (int) $id);
        if ($policy === null) {
            Session::flash('error', 'Policy not found.');
            return $this->redirect('/app/approvals/policies');
        }

        $settings = $this->db()->fetch(
            'SELECT default_min_approvals, require_dual_approval FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $sid]
        ) ?? [];

        return $this->render('stokvel/approvals/policies', [
            'title' => 'Edit approval threshold',
            'activeNav' => 'approvals',
            'navContext' => 'app',
            'policies' => $approvals->listPolicies($sid),
            'approvalRequestTypes' => ApprovalService::REQUEST_TYPE_OPTIONS,
            'roleOptions' => $this->roleOptions(),
            'settings' => $settings,
            'editPolicy' => $policy,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePolicyAccess();
        $sid = $this->requireStokvel();

        [, $min, $threshold, $roles] = $this->parsePolicyInput($request);
        // Keep existing request_type; only amount/min/roles editable on update.
        try {
            /** @var ApprovalService $approvals */
            $approvals = app()->container()->get(ApprovalService::class);
            $approvals->updatePolicy($sid, (int) $id, $min, $threshold, $roles);
            Session::flash('success', 'Approval policy updated.');
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
        }

        return $this->redirect('/app/approvals/policies');
    }

    public function activate(Request $request, string $id): Response
    {
        $this->requirePolicyAccess();
        $sid = $this->requireStokvel();
        try {
            /** @var ApprovalService $approvals */
            $approvals = app()->container()->get(ApprovalService::class);
            $approvals->setPolicyActive($sid, (int) $id, true);
            Session::flash('success', 'Policy activated.');
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect('/app/approvals/policies');
    }

    public function deactivate(Request $request, string $id): Response
    {
        $this->requirePolicyAccess();
        $sid = $this->requireStokvel();
        try {
            /** @var ApprovalService $approvals */
            $approvals = app()->container()->get(ApprovalService::class);
            $approvals->setPolicyActive($sid, (int) $id, false);
            Session::flash('success', 'Policy deactivated.');
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect('/app/approvals/policies');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePolicyAccess();
        $sid = $this->requireStokvel();
        try {
            /** @var ApprovalService $approvals */
            $approvals = app()->container()->get(ApprovalService::class);
            $approvals->deletePolicy($sid, (int) $id);
            Session::flash('success', 'Policy deleted.');
        } catch (FinanceException $e) {
            Session::flash('error', $e->getMessage());
        }
        return $this->redirect('/app/approvals/policies');
    }

    private function requirePolicyAccess(): void
    {
        if ($this->tenant()->can('settings.manage') || $this->tenant()->can('approvals.action')) {
            return;
        }
        $this->requirePermission('settings.manage');
    }

    /** @return list<array{code:string,name:string}> */
    private function roleOptions(): array
    {
        $roleOptions = $this->db()->fetchAll(
            "SELECT code, name FROM roles
             WHERE stokvel_id IS NULL
               AND code IN ('chairperson','treasurer','secretary','committee_member','ordinary_member','auditor')
             ORDER BY FIELD(code,'chairperson','treasurer','secretary','committee_member','ordinary_member','auditor')"
        );
        if ($roleOptions !== []) {
            return $roleOptions;
        }
        return [
            ['code' => 'chairperson', 'name' => 'Chairperson'],
            ['code' => 'treasurer', 'name' => 'Treasurer'],
            ['code' => 'secretary', 'name' => 'Secretary'],
            ['code' => 'committee_member', 'name' => 'Committee member'],
            ['code' => 'ordinary_member', 'name' => 'Ordinary member'],
            ['code' => 'auditor', 'name' => 'Auditor'],
        ];
    }

    /**
     * @return array{0:?string,1:int,2:?string,3:list<string>}
     */
    private function parsePolicyInput(Request $request): array
    {
        $policyType = trim((string) $request->input('policy_request_type', ''));
        $policyTypeCustom = trim((string) $request->input('policy_request_type_custom', ''));
        if ($policyType === '__custom__' && $policyTypeCustom !== '') {
            $policyType = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $policyTypeCustom) ?? $policyTypeCustom);
        } elseif ($policyType === '__custom__') {
            $policyType = '';
        }
        $knownTypes = array_keys(ApprovalService::REQUEST_TYPE_OPTIONS);
        if ($policyType !== '' && !in_array($policyType, $knownTypes, true) && !preg_match('/^[a-z0-9_]{2,64}$/', $policyType)) {
            $policyType = '';
        }

        $min = max(1, (int) $request->input('policy_min_approvals', 1));
        $thresholdRaw = trim((string) $request->input('policy_amount_threshold', ''));
        $threshold = $thresholdRaw !== '' ? $thresholdRaw : null;

        $policyRolesInput = $request->input('policy_required_roles', []);
        if (!is_array($policyRolesInput)) {
            $policyRolesInput = $policyRolesInput !== '' && $policyRolesInput !== null
                ? preg_split('/\s*,\s*/', (string) $policyRolesInput) ?: []
                : [];
        }
        $roles = array_values(array_intersect(
            array_values(array_unique(array_filter(array_map(
                static fn ($v) => strtolower(trim((string) $v)),
                $policyRolesInput
            )))),
            self::ALLOWED_ROLE_CODES
        ));

        return [$policyType !== '' ? $policyType : null, $min, $threshold, $roles];
    }

    private function saveDefaultMinApprovals(int $stokvelId, int $defaultMin): void
    {
        $now = now_utc();
        $existing = $this->db()->fetch(
            'SELECT id FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        );
        if ($existing) {
            $this->db()->exec(
                'UPDATE stokvel_settings SET default_min_approvals = :min, updated_at = :ua WHERE stokvel_id = :sid',
                ['min' => $defaultMin, 'ua' => $now, 'sid' => $stokvelId]
            );
            return;
        }
        $this->db()->insert(
            'INSERT INTO stokvel_settings (stokvel_id, default_min_approvals, created_at, updated_at)
             VALUES (:sid, :min, :ca, :ua)',
            ['sid' => $stokvelId, 'min' => $defaultMin, 'ca' => $now, 'ua' => $now]
        );
    }
}
