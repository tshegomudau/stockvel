<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Request;
use App\Core\TenantContext as CoreTenantContext;
use App\Exceptions\TenantException;
use App\Services\Auth\AuthService;
use App\Services\TenantContext;
use App\Services\PermissionService;
use App\Support\ModuleRegistry;

final class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly TenantContext $tenantContext,
        private readonly CoreTenantContext $coreTenant,
        private readonly PermissionService $permissions,
        private readonly Database $db,
    ) {
    }

    public function handle(Request $request, callable $next): mixed
    {
        if (!$this->auth->check()) {
            throw new TenantException('Authentication required for tenant context.');
        }

        $user = $this->auth->user();
        $isPlatformAdmin = user_is_platform_admin(is_array($user) ? $user : null);

        $stokvelId = $this->tenantContext->getActiveStokvelId();
        if ($stokvelId === null) {
            $memberships = array_values(array_filter(
                $this->auth->membershipsForUser((int) $this->auth->id()),
                static fn (array $m): bool => in_array((string) ($m['status'] ?? ''), ['active', 'pending'], true)
            ));
            if ($memberships === []) {
                if ($isPlatformAdmin) {
                    return \App\Core\Response::redirect(url('/platform'));
                }
                // New accounts without a membership land on get-started instead of a bare 403.
                return \App\Core\Response::redirect(url('/account/get-started'));
            }
            if (count($memberships) > 1) {
                $path = $request->path();
                if ($path !== '/account/select-stokvel' && !str_starts_with($path, '/account/select-stokvel')) {
                    \App\Core\Session::set('intended_url', '/' . ltrim($path, '/'));
                }
                return \App\Core\Response::redirect(url('/account/select-stokvel'));
            }
            $stokvelId = (int) $memberships[0]['stokvel_id'];
            $this->tenantContext->setActiveStokvelId($stokvelId);
        }

        $membership = $this->tenantContext->resolveMembershipForUser(
            (int) $this->auth->id(),
            $stokvelId
        );

        $stokvel = $this->tenantContext->getStokvel($stokvelId);
        if ($stokvel === null) {
            throw new TenantException('Stokvel not found.');
        }

        // Platform-suspended tenants cannot be used by members (platform admins use /platform).
        if ((string) ($stokvel['status'] ?? '') === 'suspended') {
            $this->tenantContext->setActiveStokvelId(null);
            if ($isPlatformAdmin) {
                return \App\Core\Response::redirect(url('/platform'));
            }
            return \App\Core\Response::redirect(url('/account/stokvel-suspended'));
        }

        $this->coreTenant->setUser($user);

        // Platform staff may open a tenant without a membership row.
        if ($membership === null) {
            if (!$isPlatformAdmin) {
                throw new TenantException('You are not a member of the selected stokvel.');
            }
            $permissionCodes = $this->permissions->forPlatformRole((string) ($user['platform_role'] ?? ''));
            $enabledModules = $this->loadEnabledModules($stokvelId);
            $this->coreTenant->setTenant($stokvel, null, $permissionCodes, $enabledModules);
            $request->setAttribute('stokvel_id', $stokvelId);
            $request->setAttribute('membership', null);
            $request->setAttribute('stokvel', $stokvel);
            return $next($request);
        }

        $membershipStatus = (string) ($membership['status'] ?? '');
        // suspended members must not retain portal access; pending = invite accepted, awaiting activation.
        if ($membershipStatus === 'suspended' && !$isPlatformAdmin) {
            throw new TenantException('Your membership is suspended for this stokvel.');
        }
        if (!in_array($membershipStatus, ['active', 'pending'], true) && !$isPlatformAdmin) {
            throw new TenantException('Your membership is not active for this stokvel.');
        }

        // Pending members may only use the member portal — never /app management routes.
        $path = $request->path();
        if ($membershipStatus === 'pending' && str_starts_with($path, '/app') && !$isPlatformAdmin) {
            return \App\Core\Response::redirect(url('/member'));
        }

        $permissionCodes = $this->permissions->codesForMembership($membership);
        if ($isPlatformAdmin) {
            $platformCodes = $this->permissions->forPlatformRole((string) ($user['platform_role'] ?? ''));
            $permissionCodes = array_values(array_unique(array_merge($permissionCodes, $platformCodes)));
        }
        if ($membershipStatus === 'pending' && !$isPlatformAdmin) {
            $permissionCodes = $this->limitPendingPermissions($permissionCodes);
        }
        $enabledModules = $this->loadEnabledModules($stokvelId);

        $this->coreTenant->setTenant($stokvel, $membership, $permissionCodes, $enabledModules);

        $request->setAttribute('stokvel_id', $stokvelId);
        $request->setAttribute('membership', $membership);
        $request->setAttribute('stokvel', $stokvel);

        return $next($request);
    }

    /**
     * Pending invitees must not inherit committee/finance powers before activation.
     *
     * @param list<string> $codes
     * @return list<string>
     */
    private function limitPendingPermissions(array $codes): array
    {
        $blockedPrefixes = [
            'contributions.', 'funds.', 'fines.', 'expenses.', 'loans.', 'payouts.',
            'fees.', 'members.', 'settings.', 'roles.', 'approvals.', 'reports.',
            'documents.manage', 'meetings.manage', 'rules.manage', 'burial.', 'grocery.',
        ];

        $out = [];
        foreach ($codes as $code) {
            $code = (string) $code;
            if ($code === '*' || $code === 'platform.*') {
                continue;
            }
            $blocked = false;
            foreach ($blockedPrefixes as $prefix) {
                if (str_starts_with($code, $prefix) || $code === rtrim($prefix, '.')) {
                    $blocked = true;
                    break;
                }
            }
            if (!$blocked) {
                $out[] = $code;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return array<string, bool> */
    private function loadEnabledModules(int $stokvelId): array
    {
        try {
            $row = $this->db->fetch(
                'SELECT loans_enabled, settings_json FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
                ['sid' => $stokvelId]
            );
            $json = json_decode((string) ($row['settings_json'] ?? '{}'), true);
            if (!is_array($json)) {
                $json = [];
            }

            $modules = ModuleRegistry::fromSettings($json, !empty($row['loans_enabled']));
            // Keep loans module aligned with the dedicated column (source of truth).
            $modules['loans'] = !empty($row['loans_enabled']);

            return $modules;
        } catch (\Throwable) {
            return ModuleRegistry::defaults(true);
        }
    }
}
