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

        $stokvelId = $this->tenantContext->getActiveStokvelId();
        if ($stokvelId === null) {
            $memberships = $this->auth->membershipsForUser((int) $this->auth->id());
            if ($memberships === []) {
                throw new TenantException('No active stokvel selected.');
            }
            $stokvelId = (int) $memberships[0]['stokvel_id'];
            $this->tenantContext->setActiveStokvelId($stokvelId);
        }

        $membership = $this->tenantContext->resolveMembershipForUser(
            (int) $this->auth->id(),
            $stokvelId
        );
        if ($membership === null) {
            throw new TenantException('You are not a member of the selected stokvel.');
        }

        // pending = accepted invite, waiting for admin approval (limited portal access).
        if (!in_array($membership['status'] ?? '', ['active', 'suspended', 'pending'], true)) {
            throw new TenantException('Your membership is not active for this stokvel.');
        }

        $stokvel = $this->tenantContext->getStokvel($stokvelId);
        if ($stokvel === null) {
            throw new TenantException('Stokvel not found.');
        }

        $permissionCodes = $this->permissions->codesForMembership($membership);
        $enabledModules = $this->loadEnabledModules($stokvelId);

        $this->coreTenant->setUser($this->auth->user());
        $this->coreTenant->setTenant($stokvel, $membership, $permissionCodes, $enabledModules);

        $request->setAttribute('stokvel_id', $stokvelId);
        $request->setAttribute('membership', $membership);
        $request->setAttribute('stokvel', $stokvel);

        return $next($request);
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
