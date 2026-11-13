<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;

final class SelectStokvelController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $user = $this->user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        /** @var AuthService $auth */
        $auth = app()->container()->get(AuthService::class);
        $memberships = array_values(array_filter(
            $auth->membershipsForUser((int) $user['id']),
            static fn (array $m): bool => in_array((string) ($m['status'] ?? ''), ['active', 'pending'], true)
        ));
        $isPlatform = user_is_platform_admin($user);

        if ($memberships === [] && !$isPlatform) {
            return $this->redirect(home_path_for_user($user));
        }

        if (count($memberships) === 1 && !$isPlatform) {
            $sid = (int) $memberships[0]['stokvel_id'];
            try {
                $auth->switchStokvel($sid, (int) $user['id']);
            } catch (\Throwable) {
                Session::flash('error', 'Could not open that stokvel.');
                return $this->redirect('/account/get-started');
            }
            return $this->redirect(home_path_for_user($user));
        }

        if (count($memberships) < 2 && !$isPlatform) {
            return $this->redirect(home_path_for_user($user));
        }

        return $this->render('account/select-stokvel', [
            'title' => 'Choose a workspace',
            'memberships' => $memberships,
            'user' => $user,
            'showPlatformOption' => $isPlatform,
        ], 'layouts/guest');
    }

    public function select(Request $request): Response
    {
        $this->requireAuth();
        $user = $this->user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        $raw = (string) $request->input('stokvel_id', '');
        if ($raw === 'platform' || $raw === '0') {
            if (!user_is_platform_admin($user)) {
                Session::flash('error', 'Only platform staff can open the platform console.');
                return $this->redirect('/account/select-stokvel');
            }
            Session::forget('active_stokvel_id');
            Session::forget('stokvel_id');
            try {
                app()->container()->get(\App\Services\TenantContext::class)->setActiveStokvelId(null);
                app()->container()->get(\App\Core\TenantContext::class)->setTenant(null);
            } catch (\Throwable) {
            }
            Session::flash('success', 'Opened platform console.');
            return $this->redirect('/platform');
        }

        $stokvelId = (int) $raw;
        if ($stokvelId < 1) {
            Session::flash('error', 'Select a stokvel to continue.');
            return $this->redirect('/account/select-stokvel');
        }

        /** @var AuthService $auth */
        $auth = app()->container()->get(AuthService::class);
        try {
            $auth->switchStokvel($stokvelId, (int) $user['id']);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'You are not linked to that stokvel.');
            return $this->redirect('/account/select-stokvel');
        }

        Session::set('active_stokvel_id', $stokvelId);
        Session::set('stokvel_id', $stokvelId);
        Session::flash('success', 'Switched stokvel.');

        $intended = Session::get('intended_url') ?? Session::getFlash('intended_url');
        Session::forget('intended_url');
        if (is_string($intended) && $intended !== '' && str_starts_with($intended, '/')
            && !str_starts_with($intended, '/account/select-stokvel')
            && !str_starts_with($intended, '/login')
            && !str_starts_with($intended, '/platform')) {
            return $this->redirect($intended);
        }

        return $this->redirect(home_path_for_user($user));
    }
}
