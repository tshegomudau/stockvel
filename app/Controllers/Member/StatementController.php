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

        $lines = $this->db()->fetchAll(
            'SELECT * FROM member_ledger_entries WHERE stokvel_id = :sid AND member_id = :mid ORDER BY entry_date DESC LIMIT 200',
            ['sid' => $sid, 'mid' => $membership['id']]
        );

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
