<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();

        $membership = $this->db()->fetch(
            'SELECT sm.* FROM memberships sm WHERE sm.stokvel_id = :sid AND sm.user_id = :uid LIMIT 1',
            ['sid' => $sid, 'uid' => $userId]
        );

        $membershipId = (int) ($membership['id'] ?? 0);
        $paid = (float) ($this->db()->fetch(
            "SELECT COALESCE(SUM(amount),0) AS b FROM payment_transactions
             WHERE stokvel_id = :sid AND membership_id = :mid
               AND status IN ('posted','completed','allocated','confirmed')",
            ['sid' => $sid, 'mid' => $membershipId]
        )['b'] ?? 0);
        $owed = (float) ($this->db()->fetch(
            "SELECT COALESCE(SUM(amount - amount_paid),0) AS b FROM member_charges
             WHERE stokvel_id = :sid AND membership_id = :mid
               AND status IN ('pending','partial','overdue')",
            ['sid' => $sid, 'mid' => $membershipId]
        )['b'] ?? 0);
        $balance = round($paid - $owed, 2);

        $upcoming = $this->db()->fetchAll(
            'SELECT * FROM meetings WHERE stokvel_id = :sid AND scheduled_at >= UTC_TIMESTAMP() ORDER BY scheduled_at ASC LIMIT 3',
            ['sid' => $sid]
        );

        return $this->render('member/dashboard', [
            'title' => 'My dashboard',
            'activeNav' => 'member-dashboard',
            'navContext' => 'member',
            'membership' => $membership,
            'balance' => $balance,
            'upcomingMeetings' => $upcoming,
        ]);
    }
}
