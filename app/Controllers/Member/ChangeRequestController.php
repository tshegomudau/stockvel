<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Membership\MemberChangeRequestService;

final class ChangeRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $membership = $this->currentMembership($sid);
        if ($membership === null) {
            return $this->redirect('/member');
        }

        $pending = $this->db()->fetchAll(
            'SELECT mcr.*, u.email AS requester_email
             FROM member_change_requests mcr
             JOIN users u ON u.id = mcr.requested_by_user_id
             WHERE mcr.membership_id = :mid AND mcr.stokvel_id = :sid AND mcr.status = :status
             ORDER BY mcr.created_at DESC',
            [
                'mid' => (int) $membership['id'],
                'sid' => $sid,
                'status' => 'pending_member',
            ]
        );

        return $this->render('member/change-requests/index', [
            'title' => 'Pending profile changes',
            'activeNav' => 'member-change-requests',
            'navContext' => 'member',
            'requests' => $pending,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $membership = $this->currentMembership($sid);
        if ($membership === null) {
            return $this->redirect('/member');
        }

        $change = $this->db()->fetch(
            'SELECT mcr.*, u.email AS requester_email
             FROM member_change_requests mcr
             JOIN users u ON u.id = mcr.requested_by_user_id
             WHERE mcr.id = :id AND mcr.membership_id = :mid AND mcr.stokvel_id = :sid
             LIMIT 1',
            ['id' => (int) $id, 'mid' => (int) $membership['id'], 'sid' => $sid]
        );
        if (!$change) {
            return $this->redirect('/member/change-requests');
        }

        $payload = json_decode((string) ($change['payload_json'] ?? '{}'), true);
        if (!is_array($payload)) {
            $payload = [];
        }

        return $this->render('member/change-requests/show', [
            'title' => 'Review change',
            'activeNav' => 'member-change-requests',
            'navContext' => 'member',
            'change' => $change,
            'payload' => $payload,
        ]);
    }

    public function approve(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $membership = $this->currentMembership($sid);
        if ($membership === null) {
            return $this->redirect('/member');
        }

        $notes = trim((string) $request->input('notes', ''));
        try {
            $service = app()->container()->get(MemberChangeRequestService::class);
            $service->approve((int) $id, (int) $membership['id'], $sid, $notes !== '' ? $notes : null);
            Session::flash('success', 'Change approved and applied.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not approve change.');
        }

        return $this->redirect('/member/change-requests');
    }

    public function reject(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $membership = $this->currentMembership($sid);
        if ($membership === null) {
            return $this->redirect('/member');
        }

        $notes = trim((string) $request->input('notes', ''));
        try {
            $service = app()->container()->get(MemberChangeRequestService::class);
            $service->reject((int) $id, (int) $membership['id'], $sid, $notes !== '' ? $notes : null);
            Session::flash('success', 'Change rejected.');
        } catch (\Throwable $e) {
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not reject change.');
        }

        return $this->redirect('/member/change-requests');
    }

    /** @return array<string, mixed>|null */
    private function currentMembership(int $stokvelId): ?array
    {
        return $this->db()->fetch(
            'SELECT * FROM memberships WHERE stokvel_id = :sid AND user_id = :uid LIMIT 1',
            ['sid' => $stokvelId, 'uid' => (int) $this->userId()]
        );
    }
}
