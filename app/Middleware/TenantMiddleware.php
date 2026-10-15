<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\TenantContext as CoreTenantContext;
use App\Exceptions\TenantException;
use App\Services\Auth\AuthService;
use App\Services\TenantContext;
use App\Services\PermissionService;

final class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly TenantContext $tenantContext,
        private readonly CoreTenantContext $coreTenant,
        private readonly PermissionService $permissions
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

        if (!in_array($membership['status'] ?? '', ['active', 'suspended'], true)) {
            throw new TenantException('Your membership is not active for this stokvel.');
        }

        $stokvel = $this->tenantContext->getStokvel($stokvelId);
        if ($stokvel === null) {
            throw new TenantException('Stokvel not found.');
        }

        $permissionCodes = $this->permissions->codesForMembership($membership);

        $this->coreTenant->setUser($this->auth->user());
        $this->coreTenant->setTenant($stokvel, $membership, $permissionCodes);

        $request->setAttribute('stokvel_id', $stokvelId);
        $request->setAttribute('membership', $membership);
        $request->setAttribute('stokvel', $stokvel);

        return $next($request);
    }
}
