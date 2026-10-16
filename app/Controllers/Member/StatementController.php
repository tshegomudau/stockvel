<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

final class StatementController extends Controller
{
    public function show(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();

        $membership = $this->db()->fetch(
            'SELECT * FROM memberships WHERE stokvel_id = :sid AND user_id = :uid LIMIT 1',
            ['sid' => $sid, 'uid' => $userId]
        );
        if (!$membership) {
            return $this->redirect('/member');
        }

        $membershipId = (int) $membership['id'];
        $charges = $this->db()->fetchAll(
            "SELECT due_date AS entry_date, description, amount, 'charge' AS entry_type, charge_code AS reference
             FROM member_charges
             WHERE stokvel_id = :sid AND membership_id = :mid
             ORDER BY due_date DESC, id DESC LIMIT 100",
            ['sid' => $sid, 'mid' => $membershipId]
        );
        $payments = $this->db()->fetchAll(
            "SELECT paid_at AS entry_date, CONCAT('Payment ', COALESCE(payment_method, '')) AS description,
                    amount, 'payment' AS entry_type, COALESCE(transaction_ref, CAST(id AS CHAR)) AS reference
             FROM payment_transactions
             WHERE stokvel_id = :sid AND membership_id = :mid
               AND status IN ('posted','completed','allocated','confirmed')
             ORDER BY paid_at DESC, id DESC LIMIT 100",
            ['sid' => $sid, 'mid' => $membershipId]
        );
        $lines = array_merge($charges, $payments);
        usort($lines, static fn (array $a, array $b): int => strcmp((string) ($b['entry_date'] ?? ''), (string) ($a['entry_date'] ?? '')));
        $lines = array_slice($lines, 0, 200);

        $layout = $request->query('print') ? 'layouts/print' : 'layouts/app';

        return $this->render('member/statement', [
            'title' => 'My statement',
            'activeNav' => 'member-statement',
            'navContext' => 'member',
            'membership' => $membership,
            'lines' => $lines,
        ], $layout);
    }
}
