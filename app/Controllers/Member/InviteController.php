<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

/**
 * Members with members.invite can invite ordinary members.
 * Only admins (members.manage) approve after acceptance.
 */
final class InviteController extends Controller
{
    public function form(Request $request): Response
    {
        $this->requirePermission('members.invite');
        $this->requireStokvel();
        $this->requireActiveMembership();

        return $this->render('stokvel/members/invite', [
            'title' => 'Invite member',
            'activeNav' => 'member-invite',
            'navContext' => 'member',
            'canAssignRoles' => $this->tenant()->can('members.manage'),
            'inviteAction' => '/member/invite',
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('members.invite');
        $this->requireStokvel();
        $this->requireActiveMembership();
        $canAssignRoles = $this->tenant()->can('members.manage');

        $data = [
            'email' => trim((string) $request->input('email', '')),
            'mobile' => trim((string) $request->input('mobile', '')),
            'role' => (string) $request->input('role', 'ordinary'),
        ];
        if (!$canAssignRoles) {
            $data['role'] = 'ordinary';
        }

        $roleMap = [
            'ordinary' => 'ordinary_member',
            'committee' => 'committee_member',
        ];
        $roleCode = $roleMap[$data['role']] ?? $data['role'];
        $allowedRoles = $canAssignRoles
            ? 'chairperson,treasurer,secretary,committee,ordinary,auditor,ordinary_member,committee_member'
            : 'ordinary,ordinary_member';

        $validator = Validator::make($data, [
            'email' => 'required|email',
            'role' => 'required|in:' . $allowedRoles,
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', $validator->firstError() ?? 'Could not send invitation.');
            return $this->redirect('/member/invite');
        }

        $role = $this->db()->fetch(
            'SELECT id FROM roles WHERE code = :code AND stokvel_id IS NULL LIMIT 1',
            ['code' => $roleCode]
        );

        try {
            /** @var \App\Services\Membership\MembershipService $memberships */
            $memberships = app()->container()->get(\App\Services\Membership\MembershipService::class);
            $memberships->invite(
                $data['email'],
                $data['mobile'] !== '' ? $data['mobile'] : null,
                $role ? (int) $role['id'] : null,
                (int) $this->userId()
            );
        } catch (\Throwable $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not send invitation.');
            return $this->redirect('/member/invite');
        }

        Session::flash('success', 'Invitation sent. An admin must approve them after they accept.');
        return $this->redirect('/member');
    }

    private function requireActiveMembership(): void
    {
        $membership = $this->tenant()->membership();
        if (($membership['status'] ?? '') !== 'active') {
            throw new \App\Exceptions\HttpException(403, 'Your membership must be active before you can invite others.');
        }
    }
}
