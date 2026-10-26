<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Finance\ChartOfAccountsService;
use App\Services\Finance\JoiningFeeService;
use App\Support\MoneyMath;
use App\Support\StokvelTypeCatalog;

final class SettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->requirePermission('settings.manage');
        $sid = $this->requireStokvel();
        app()->container()->get(ChartOfAccountsService::class)->ensureDefaultAccounts($sid);

        $stokvel = $this->db()->fetch('SELECT * FROM stokvels WHERE id = :id LIMIT 1', ['id' => $sid]);
        $settings = $this->db()->fetch('SELECT * FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1', ['sid' => $sid]) ?? [];
        $onboarding = $this->db()->fetch(
            'SELECT * FROM stokvel_onboarding WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $sid]
        );
        /** @var JoiningFeeService $joiningFees */
        $joiningFees = app()->container()->get(JoiningFeeService::class);
        $joiningFeeSettings = $joiningFees->ensureSettings($sid);

        return $this->render('stokvel/settings/edit', [
            'title' => 'Settings',
            'activeNav' => 'settings',
            'navContext' => 'app',
            'stokvel' => $stokvel,
            'settings' => $settings,
            'onboarding' => $onboarding,
            'joiningFeeSettings' => $joiningFeeSettings,
            'stokvelTypeOptions' => StokvelTypeCatalog::activeOptions($this->db()),
        ]);
    }

    public function update(Request $request): Response
    {
        $this->requirePermission('settings.manage');
        $sid = $this->requireStokvel();
        $now = now_utc();

        $name = trim((string) $request->input('name', ''));
        $reg = trim((string) $request->input('registration_number', ''));
        $desc = trim((string) $request->input('description', ''));
        if ($name === '') {
            $this->flashOld($request);
            Session::flash('error', 'Organisation name is required.');
            return $this->redirect('/app/settings');
        }
        $yearEnd = (int) $request->input('year_end_month', 12);
        if ($yearEnd < 1 || $yearEnd > 12) {
            $this->flashOld($request);
            Session::flash('error', 'Year-end month must be between 1 and 12.');
            return $this->redirect('/app/settings');
        }
        $stokvelType = trim((string) $request->input('stokvel_type', ''));
        if ($stokvelType === '' || !StokvelTypeCatalog::isValidCode($stokvelType, true, $this->db())) {
            $current = $this->db()->fetch('SELECT stokvel_type FROM stokvels WHERE id = :id LIMIT 1', ['id' => $sid]);
            $stokvelType = (string) ($current['stokvel_type'] ?? 'general_savings');
            if (!StokvelTypeCatalog::isValidCode($stokvelType, false, $this->db())) {
                $stokvelType = 'general_savings';
            }
        }
        // Features/governance stay platform-managed; each stokvel chooses its type from the catalogue.
        $this->db()->exec(
            'UPDATE stokvels SET name = :name, registration_number = :reg, description = :desc,
                stokvel_type = :type, updated_at = :ua WHERE id = :id',
            [
                'name' => $name,
                'reg' => $reg !== '' ? $reg : null,
                'desc' => $desc !== '' ? $desc : null,
                'type' => $stokvelType,
                'ua' => $now,
                'id' => $sid,
            ]
        );

        if ($request->input('activate_stokvel')) {
            $this->db()->exec(
                "UPDATE stokvels SET status = 'active', activated_at = COALESCE(activated_at, :aa), updated_at = :ua WHERE id = :id",
                ['aa' => $now, 'ua' => $now, 'id' => $sid]
            );
            $this->db()->exec(
                'UPDATE stokvel_onboarding SET completed_at = COALESCE(completed_at, :ca), current_step = 99, updated_at = :ua
                 WHERE stokvel_id = :sid',
                ['ca' => $now, 'ua' => $now, 'sid' => $sid]
            );
        }

        $joiningFeeAmount = '0.00';
        try {
            $rawJoining = $request->input('joining_fee_amount', '0');
            $joiningFeeAmount = MoneyMath::normalize($rawJoining !== '' && $rawJoining !== null ? (string) $rawJoining : '0');
        } catch (\Throwable) {
            $joiningFeeAmount = '0.00';
        }

        // Threshold policies + default_min_approvals are managed on /app/approvals/policies.
        $existingSettings = $this->db()->fetch(
            'SELECT id, default_min_approvals FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $sid]
        );
        $defaultMinApprovals = max(1, (int) ($existingSettings['default_min_approvals'] ?? (!empty($existingSettings['require_dual_approval']) ? 2 : 1)));

        $settings = [
            'contribution_frequency' => (string) $request->input('contribution_frequency', 'monthly'),
            'contribution_day' => (int) $request->input('payment_due_day', 1) ?: null,
            'payment_due_day' => (int) $request->input('payment_due_day', 1) ?: null,
            'minimum_contribution' => $request->input('minimum_contribution') !== '' && $request->input('minimum_contribution') !== null
                ? (string) $request->input('minimum_contribution') : null,
            'joining_fee_amount' => $joiningFeeAmount,
            'grace_period_days' => (int) $request->input('grace_period_days', 7),
            'arrears_after_days' => (int) $request->input('arrears_after_days', 7),
            'year_end_month' => $yearEnd,
            'default_min_approvals' => $defaultMinApprovals,
            'bank_name' => trim((string) $request->input('bank_name', '')) ?: null,
            'bank_account_name' => trim((string) $request->input('bank_account_name', '')) ?: null,
            'bank_account_number_masked' => trim((string) $request->input('bank_account_number_masked', '')) ?: null,
            'bank_branch_code' => trim((string) $request->input('bank_branch_code', '')) ?: null,
            'branding_primary_colour' => trim((string) $request->input('branding_primary_colour', '')) ?: null,
            'updated_at' => $now,
        ];

        if ($existingSettings) {
            $sets = [];
            foreach (array_keys($settings) as $col) {
                $sets[] = "{$col} = :{$col}";
            }
            $settings['sid'] = $sid;
            $this->db()->exec('UPDATE stokvel_settings SET ' . implode(', ', $sets) . ' WHERE stokvel_id = :sid', $settings);
        } else {
            $settings['stokvel_id'] = $sid;
            $settings['created_at'] = $now;
            $cols = array_keys($settings);
            $ph = array_map(static fn ($c) => ':' . $c, $cols);
            $this->db()->insert(
                'INSERT INTO stokvel_settings (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')',
                $settings
            );
        }

        /** @var JoiningFeeService $joiningFees */
        $joiningFees = app()->container()->get(JoiningFeeService::class);
        $joiningFees->ensureSettings($sid);
        $this->db()->exec(
            'UPDATE joining_fee_settings
             SET amount = :amt,
                 is_compulsory = :comp,
                 is_once_off = :once,
                 charge_on_approval = :coa,
                 charge_on_anniversary = :cann,
                 is_active = :active,
                 updated_at = :ua
             WHERE stokvel_id = :sid',
            [
                'amt' => $joiningFeeAmount,
                'comp' => $request->input('joining_is_compulsory') ? 1 : 0,
                'once' => $request->input('joining_is_once_off') ? 1 : 0,
                'coa' => $request->input('joining_charge_on_approval') ? 1 : 0,
                'cann' => $request->input('joining_charge_on_anniversary') ? 1 : 0,
                'active' => $request->input('joining_is_active') ? 1 : 0,
                'ua' => $now,
                'sid' => $sid,
            ]
        );

        app()->container()->get(ChartOfAccountsService::class)->ensureDefaultAccounts($sid);
        Session::flash('success', 'Settings saved.');
        return $this->redirect('/app/settings');
    }
}
