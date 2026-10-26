<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Finance\FineService;

final class FineController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $membership = $this->db()->fetch(
            'SELECT * FROM memberships WHERE stokvel_id = :sid AND user_id = :uid LIMIT 1',
            ['sid' => $sid, 'uid' => (int) $this->userId()]
        );
        if (!$membership) {
            return $this->redirect('/member');
        }

        /** @var FineService $fines */
        $fines = app()->container()->get(FineService::class);
        $list = $fines->listForMember($sid, (int) $membership['id']);

        return $this->render('member/fines/index', [
            'title' => 'My fines',
            'activeNav' => 'member-fines',
            'navContext' => 'member',
            'fines' => $list,
        ]);
    }
}
