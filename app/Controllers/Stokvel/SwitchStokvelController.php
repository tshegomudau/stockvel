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

        $stokvelId = (int) $request->input('stokvel_id', 0);
        $userId = (int) $user['id'];

        /** @var AuthService $auth */
        $auth = app()->container()->get(AuthService::class);
        $memberships = array_values(array_filter(
            $auth->membershipsForUser($userId),
            static fn (array $m): bool => in_array((string) ($m['status'] ?? ''), ['active', 'pending'], true)
        ));

        if (count($memberships) < 2 && !$this->tenant()->isPlatformAdmin()) {
            Session::flash('error', 'You are only linked to one stokvel.');
            return $this->redirect(home_path_for_user($user));
        }

        $allowed = false;
        foreach ($memberships as $m) {
            if ((int) ($m['stokvel_id'] ?? 0) === $stokvelId) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed && !$this->tenant()->isPlatformAdmin()) {
            Session::flash('error', 'You are not a member of that stokvel.');
            return $this->redirectBack($request, home_path_for_user($user));
        }

        try {
            $auth->switchStokvel($stokvelId, $userId);
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not switch stokvel.');
            return $this->redirectBack($request, home_path_for_user($user));
        }

        Session::flash('success', 'Active stokvel updated.');

        $redirect = (string) $request->input('redirect', '');
        if ($redirect !== '' && str_starts_with($redirect, '/')
            && !str_starts_with($redirect, '//')
            && !str_starts_with($redirect, '/account/select-stokvel')) {
            return $this->redirect($redirect);
        }

        return $this->redirect(home_path_for_user($user));
    }
}
