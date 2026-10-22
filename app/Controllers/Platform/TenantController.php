<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Support\ModuleRegistry;
use App\Support\StokvelTypeCatalog;

final class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }

        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $search = trim((string) $request->query('q', ''));
        $params = [];
        $where = '1=1';
        if ($search !== '') {
            $where .= ' AND (name LIKE :q OR registration_number LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }

        $paginator = Paginator::fromQuery(
            $this->db(),
            "SELECT COUNT(*) AS c FROM stokvels WHERE {$where}",
            "SELECT id, name, status, created_at, registration_number FROM stokvels WHERE {$where} ORDER BY created_at DESC",
            $params,
            $page,
            $perPage,
            url('/platform/tenants'),
            $search !== '' ? ['q' => $search] : []
        );

        return $this->render('platform/tenants/index', [
            'title' => 'Stokvels',
            'activeNav' => 'platform-tenants',
            'navContext' => 'platform',
            'paginator' => $paginator,
            'search' => $search,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }

        $stokvel = $this->db()->fetch('SELECT * FROM stokvels WHERE id = :id LIMIT 1', ['id' => (int) $id]);
        if (!$stokvel) {
            return $this->redirect('/platform/tenants');
        }

        $stokvelId = (int) $id;
        $members = $this->db()->fetchAll(
            'SELECT sm.*, p.first_name, p.last_name, u.email FROM memberships sm JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE sm.stokvel_id = :sid ORDER BY sm.created_at DESC LIMIT 50',
            ['sid' => $stokvelId]
        );

        $chairperson = $this->db()->fetch(
            "SELECT sm.id AS membership_id, u.email, p.first_name, p.last_name
             FROM memberships sm
             INNER JOIN membership_roles mr ON mr.membership_id = sm.id
             INNER JOIN roles r ON r.id = mr.role_id AND r.code = 'chairperson'
             INNER JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE sm.stokvel_id = :sid
             LIMIT 1",
            ['sid' => $stokvelId]
        );

        $activeMembers = $this->db()->fetchAll(
            "SELECT sm.id AS membership_id, sm.user_id, sm.member_number,
                    u.email, p.first_name, p.last_name
             FROM memberships sm
             INNER JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE sm.stokvel_id = :sid AND sm.status = 'active'
             ORDER BY p.last_name ASC, p.first_name ASC, u.email ASC",
            ['sid' => $stokvelId]
        );

        $settings = $this->db()->fetch(
            'SELECT * FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        ) ?? [];
        $json = json_decode((string) ($settings['settings_json'] ?? '{}'), true);
        if (!is_array($json)) {
            $json = [];
        }
        $enabledModules = ModuleRegistry::fromSettings($json, !empty($settings['loans_enabled']));

        return $this->render('platform/tenants/show', [
            'title' => $stokvel['name'] ?? 'Stokvel',
            'activeNav' => 'platform-tenants',
            'navContext' => 'platform',
            'stokvel' => $stokvel,
            'members' => $members,
            'chair' => $chairperson,
            'chairperson' => $chairperson,
            'activeMembers' => $activeMembers,
            'settings' => $settings,
            'enabledModules' => $enabledModules,
            'moduleCatalog' => ModuleRegistry::MODULES,
            'canManageModules' => $this->tenant()->isSuperAdmin(),
            'canAppointChairperson' => $this->tenant()->isSuperAdmin() && $chairperson === null,
            'stokvelTypes' => StokvelTypeCatalog::labelMap($this->db()),
        ]);
    }

    public function updateGovernance(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            Session::flash('error', 'Only platform super administrators can change governance.');
            return $this->redirect('/platform/tenants/' . $id);
        }

        $stokvelId = (int) $id;
        $stokvel = $this->db()->fetch('SELECT id FROM stokvels WHERE id = :id LIMIT 1', ['id' => $stokvelId]);
        if (!$stokvel) {
            return $this->redirect('/platform/tenants');
        }

        // Stokvel type is chosen by the stokvel in app settings from the platform catalogue.
        $now = now_utc();
        $loansEnabled = $request->input('loans_enabled') ? 1 : 0;
        $dual = $request->input('require_dual_approval') ? 1 : 0;
        $emailOn = $request->input('notification_email_enabled') ? 1 : 0;
        $smsOn = $request->input('notification_sms_enabled') ? 1 : 0;
        $payoutMode = (string) $request->input('payout_mode', 'rotational');
        if (!in_array($payoutMode, ['rotational', 'equal_distribution', 'custom'], true)) {
            $payoutMode = 'rotational';
        }

        $existing = $this->db()->fetch(
            'SELECT id, settings_json FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        );
        $json = json_decode((string) ($existing['settings_json'] ?? '{}'), true);
        if (!is_array($json)) {
            $json = [];
        }
        if (!isset($json['enabled_modules']) || !is_array($json['enabled_modules'])) {
            $json['enabled_modules'] = ModuleRegistry::defaults((bool) $loansEnabled);
        } else {
            $json['enabled_modules']['loans'] = (bool) $loansEnabled;
        }
        $encoded = json_encode($json, JSON_THROW_ON_ERROR);

        if ($existing) {
            $this->db()->exec(
                'UPDATE stokvel_settings SET
                    loans_enabled = :loans,
                    require_dual_approval = :dual,
                    notification_email_enabled = :email,
                    notification_sms_enabled = :sms,
                    payout_mode = :payout,
                    settings_json = :json,
                    updated_at = :ua
                 WHERE id = :id',
                [
                    'loans' => $loansEnabled,
                    'dual' => $dual,
                    'email' => $emailOn,
                    'sms' => $smsOn,
                    'payout' => $payoutMode,
                    'json' => $encoded,
                    'ua' => $now,
                    'id' => (int) $existing['id'],
                ]
            );
        } else {
            $this->db()->insert(
                'INSERT INTO stokvel_settings
                    (stokvel_id, loans_enabled, require_dual_approval, notification_email_enabled,
                     notification_sms_enabled, payout_mode, settings_json, created_at, updated_at)
                 VALUES
                    (:sid, :loans, :dual, :email, :sms, :payout, :json, :ca, :ua)',
                [
                    'sid' => $stokvelId,
                    'loans' => $loansEnabled,
                    'dual' => $dual,
                    'email' => $emailOn,
                    'sms' => $smsOn,
                    'payout' => $payoutMode,
                    'json' => $encoded,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
        }

        Session::flash('success', 'Governance updated.');
        return $this->redirect('/platform/tenants/' . $stokvelId);
    }

    public function updateModules(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            Session::flash('error', 'Only platform super administrators can change modules.');
            return $this->redirect('/platform/tenants/' . $id);
        }

        $stokvelId = (int) $id;
        $stokvel = $this->db()->fetch('SELECT id FROM stokvels WHERE id = :id LIMIT 1', ['id' => $stokvelId]);
        if (!$stokvel) {
            return $this->redirect('/platform/tenants');
        }

        $posted = $request->input('modules', []);
        if (!is_array($posted)) {
            $posted = [];
        }

        $enabled = [];
        foreach (array_keys(ModuleRegistry::MODULES) as $code) {
            $enabled[$code] = !empty($posted[$code]);
        }

        $settings = $this->db()->fetch(
            'SELECT id, settings_json FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        );
        $json = json_decode((string) ($settings['settings_json'] ?? '{}'), true);
        if (!is_array($json)) {
            $json = [];
        }
        $json['enabled_modules'] = $enabled;
        $encoded = json_encode($json, JSON_THROW_ON_ERROR);
        $now = now_utc();
        $loansEnabled = !empty($enabled['loans']) ? 1 : 0;

        if ($settings) {
            $this->db()->exec(
                'UPDATE stokvel_settings SET settings_json = :json, loans_enabled = :loans, updated_at = :ua WHERE id = :id',
                ['json' => $encoded, 'loans' => $loansEnabled, 'ua' => $now, 'id' => (int) $settings['id']]
            );
        } else {
            $this->db()->insert(
                'INSERT INTO stokvel_settings (stokvel_id, loans_enabled, settings_json, created_at, updated_at)
                 VALUES (:sid, :loans, :json, :ca, :ua)',
                ['sid' => $stokvelId, 'loans' => $loansEnabled, 'json' => $encoded, 'ca' => $now, 'ua' => $now]
            );
        }

        Session::flash('success', 'Modules updated for this stokvel.');
        return $this->redirect('/platform/tenants/' . $stokvelId);
    }

    public function suspend(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            return $this->redirect('/platform/tenants/' . $id);
        }
        $this->db()->exec('UPDATE stokvels SET status = :st, updated_at = :ua WHERE id = :id', [
            'st' => 'suspended',
            'ua' => now_utc(),
            'id' => (int) $id,
        ]);
        Session::flash('success', 'Stokvel suspended.');
        return $this->redirect('/platform/tenants/' . $id);
    }

    public function activate(Request $request, string $id): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isSuperAdmin()) {
            return $this->redirect('/platform/tenants/' . $id);
        }
        $this->db()->exec('UPDATE stokvels SET status = :st, updated_at = :ua WHERE id = :id', [
            'st' => 'active',
            'ua' => now_utc(),
            'id' => (int) $id,
        ]);
        Session::flash('success', 'Stokvel activated.');
        return $this->redirect('/platform/tenants/' . $id);
    }
}
