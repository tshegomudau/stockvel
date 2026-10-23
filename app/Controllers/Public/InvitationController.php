<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
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

        return $this->render('public/invitation', [
            'title' => 'Stokvel invitation',
            'token' => $token,
            'invitation' => $invitation,
        ], 'layouts/guest');
    }

    public function accept(Request $request, string $token): Response
    {
        $userId = $this->userId();
        if ($userId === null) {
            Session::flash('intended_url', '/invitations/' . $token);
            Session::flash('error', 'Sign in or register to accept this invitation.');
            return $this->redirect('/login');
        }

        try {
            /** @var MembershipService $memberships */
            $memberships = app()->container()->get(MembershipService::class);
            $memberships->accept($token, (int) $userId);
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/');
        }

        Session::flash('success', 'Invitation accepted. An admin must approve your membership before you can use the stokvel.');
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
