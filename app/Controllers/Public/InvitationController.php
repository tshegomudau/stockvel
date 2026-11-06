<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Services\Auth\AuthService;
use App\Services\Membership\InvitationService;
use App\Services\Membership\MembershipService;

final class InvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        /** @var InvitationService $invitations */
        $invitations = app()->container()->get(InvitationService::class);
        $invitation = $invitations->findByToken($token);

        if ($invitation === null || ($invitation['status'] ?? '') !== 'pending') {
            Session::flash('error', 'This invitation is invalid or has already been used.');
            return $this->redirect('/');
        }

        if ($invitations->isExpired($invitation)) {
            Session::flash('error', 'This invitation has expired.');
            return $this->redirect('/');
        }

        $stokvel = $this->db()->fetch(
            'SELECT name FROM stokvels WHERE id = :id LIMIT 1',
            ['id' => (int) $invitation['stokvel_id']]
        );
        $invitation['stokvel_name'] = $stokvel['name'] ?? 'Stokvel';

        $roleName = null;
        if (!empty($invitation['role_id'])) {
            $role = $this->db()->fetch(
                'SELECT name, code FROM roles WHERE id = :id LIMIT 1',
                ['id' => (int) $invitation['role_id']]
            );
            $roleName = $role['name'] ?? ($role['code'] ?? null);
        }
        $invitation['role'] = $roleName ?? 'ordinary member';

        // Persist across login/register (flash alone can be lost on intermediate pages).
        Session::set('intended_url', '/invitations/' . $token);

        return $this->render('public/invitation', [
            'title' => 'Stokvel invitation',
            'token' => $token,
            'invitation' => $invitation,
            'isLoggedIn' => $this->userId() !== null,
            'currentUser' => $this->user(),
        ], 'layouts/guest');
    }

    public function accept(Request $request, string $token): Response
    {
        $userId = $this->userId();
        if ($userId === null) {
            Session::set('intended_url', '/invitations/' . $token);
            Session::flash('error', 'Sign in or register to accept this invitation.');
            return $this->redirect('/login');
        }

        try {
            /** @var InvitationService $invitations */
            $invitations = app()->container()->get(InvitationService::class);
            $invitation = $invitations->findByToken($token);
            $inviteStokvelId = (int) ($invitation['stokvel_id'] ?? 0);

            /** @var MembershipService $memberships */
            $memberships = app()->container()->get(MembershipService::class);
            $membership = $memberships->accept($token, (int) $userId);
            $stokvelId = (int) ($membership['stokvel_id'] ?? $inviteStokvelId);

            if ($stokvelId > 0) {
                // Always pin session to the invited stokvel (pending memberships are allowed).
                Session::set('active_stokvel_id', $stokvelId);
                Session::set('stokvel_id', $stokvelId);
                try {
                    /** @var AuthService $auth */
                    $auth = app()->container()->get(AuthService::class);
                    $auth->switchStokvel($stokvelId, (int) $userId);
                } catch (HttpException) {
                    // Session keys above are enough for TenantMiddleware.
                }
            }
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/invitations/' . rawurlencode($token));
        }

        Session::flash('success', 'Invitation accepted. An admin must approve your membership before full access is unlocked.');
        return $this->redirect('/member');
    }

    public function reject(Request $request, string $token): Response
    {
        $userId = $this->userId();
        try {
            /** @var MembershipService $memberships */
            $memberships = app()->container()->get(MembershipService::class);
            $memberships->reject($token, (int) ($userId ?? 0));
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/');
        }

        Session::flash('success', 'Invitation declined.');
        return $this->redirect('/');
    }
}
