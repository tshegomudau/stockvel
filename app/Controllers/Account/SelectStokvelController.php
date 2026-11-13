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

        if ($memberships === []) {
            return $this->redirect(home_path_for_user($user));
        }

        if (count($memberships) === 1) {
            $sid = (int) $memberships[0]['stokvel_id'];
            try {
                $auth->switchStokvel($sid, (int) $user['id']);
            } catch (\Throwable) {
                Session::flash('error', 'Could not open that stokvel.');
                return $this->redirect('/account/get-started');
            }
            return $this->redirect(home_path_for_user($user));
        }

        return $this->render('account/select-stokvel', [
            'title' => 'Choose a stokvel',
            'memberships' => $memberships,
            'user' => $user,
        ], 'layouts/guest');
    }

    public function select(Request $request): Response
    {
        $this->requireAuth();
        $user = $this->user();
        if ($user === null) {
            return $this->redirect('/login');
        }

        $stokvelId = (int) $request->input('stokvel_id', 0);
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

        Session::flash('success', 'Switched stokvel.');

        $intended = Session::get('intended_url') ?? Session::getFlash('intended_url');
        Session::forget('intended_url');
        if (is_string($intended) && $intended !== '' && str_starts_with($intended, '/')
            && !str_starts_with($intended, '/account/select-stokvel')
            && !str_starts_with($intended, '/login')) {
            return $this->redirect($intended);
        }

        return $this->redirect(home_path_for_user($user));
    }
}
