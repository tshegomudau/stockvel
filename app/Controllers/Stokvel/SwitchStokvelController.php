<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class SwitchStokvelController extends Controller
{
    public function switch(Request $request): Response
    {
        $this->requireAuth();
        $stokvelId = (int) $request->input('stokvel_id', 0);
        $userId = (int) $this->userId();

        $membership = $this->db()->fetch(
            'SELECT * FROM memberships WHERE stokvel_id = :sid AND user_id = :uid AND status = :st LIMIT 1',
            ['sid' => $stokvelId, 'uid' => $userId, 'st' => 'active']
        );

        if (!$membership && !$this->tenant()->isPlatformAdmin()) {
            Session::flash('error', 'You are not a member of that stokvel.');
            return $this->redirectBack($request, '/app');
        }

        Session::regenerate(true);
        Session::set('active_stokvel_id', $stokvelId);
        Session::flash('success', 'Active stokvel updated.');

        $redirect = (string) $request->input('redirect', '/app');
        if (!str_starts_with($redirect, '/')) {
            $redirect = '/app';
        }
        return $this->redirect($redirect);
    }
}
