<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;

final class SwitchStokvelController extends Controller
{
    public function switch(Request $request): Response
    {
        $this->requireAuth();
        $user = $this->user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        $raw = (string) $request->input('stokvel_id', '');
        $userId = (int) $user['id'];
        $isPlatform = user_is_platform_admin($user);

        /** @var AuthService $auth */
        $auth = app()->container()->get(AuthService::class);
        $memberships = array_values(array_filter(
            $auth->membershipsForUser($userId),
            static fn (array $m): bool => in_array((string) ($m['status'] ?? ''), ['active', 'pending'], true)
        ));

        // Platform console: clear tenant context and return to /platform.
        if ($raw === 'platform' || $raw === '0') {
            if (!$isPlatform) {
                Session::flash('error', 'Only platform staff can open the platform console.');
                return $this->redirect(home_path_for_user($user));
            }
            Session::forget('active_stokvel_id');
            Session::forget('stokvel_id');
            try {
                app()->container()->get(\App\Services\TenantContext::class)->setActiveStokvelId(null);
                app()->container()->get(\App\Core\TenantContext::class)->setTenant(null);
            } catch (\Throwable) {
            }
            Session::flash('success', 'Switched to platform console.');
            return $this->redirect('/platform');
        }

        $stokvelId = (int) $raw;
        if ($stokvelId < 1) {
            Session::flash('error', 'Select a stokvel to continue.');
            return $this->redirectBack($request, home_path_for_user($user));
        }

        $canSwitch = $isPlatform || count($memberships) > 1;
        if (!$canSwitch) {
            Session::flash('error', 'You are only linked to one stokvel.');
            return $this->redirect(home_path_for_user($user));
        }

        $allowed = $isPlatform;
        if (!$allowed) {
            foreach ($memberships as $m) {
                if ((int) ($m['stokvel_id'] ?? 0) === $stokvelId) {
                    $allowed = true;
                    break;
                }
            }
        }
        if (!$allowed) {
            Session::flash('error', 'You are not a member of that stokvel.');
            return $this->redirectBack($request, home_path_for_user($user));
        }

        try {
            $auth->switchStokvel($stokvelId, $userId);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not switch stokvel.');
            return $this->redirectBack($request, home_path_for_user($user));
        }

        // Ensure session keys are visible to the next request's home-path resolution.
        Session::set('active_stokvel_id', $stokvelId);
        Session::set('stokvel_id', $stokvelId);

        Session::flash('success', 'Active stokvel updated.');

        $redirect = (string) $request->input('redirect', '');
        if ($redirect !== '' && str_starts_with($redirect, '/')
            && !str_starts_with($redirect, '//')
            && !str_starts_with($redirect, '/account/select-stokvel')
            && !str_starts_with($redirect, '/platform')) {
            // Stay in tenant app when switching between stokvels from an /app or /member page.
            if (str_starts_with($redirect, '/app') || str_starts_with($redirect, '/member')) {
                return $this->redirect($redirect);
            }
        }

        return $this->redirect(home_path_for_user($user));
    }
}
