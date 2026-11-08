<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth\AuthService;

/**
 * Shown when the user's only stokvel membership(s) are platform-suspended.
 */
final class StokvelSuspendedController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();
        /** @var AuthService $auth */
        $auth = app()->container()->get(AuthService::class);

        // If they gained a usable stokvel (reactivated), send them home.
        if ($auth->membershipsForUser($userId) !== []) {
            return $this->redirect(home_path_for_user($this->user()));
        }
        if (!$auth->hasOnlySuspendedStokvels($userId)) {
            return $this->redirect('/account/get-started');
        }

        $suspended = $this->db()->fetchAll(
            'SELECT s.id, s.name, s.status, m.status AS membership_status
             FROM memberships m
             INNER JOIN stokvels s ON s.id = m.stokvel_id
             WHERE m.user_id = :uid
               AND m.status IN (\'active\', \'suspended\', \'pending\')
               AND s.status = \'suspended\'
             ORDER BY s.name ASC',
            ['uid' => $userId]
        );

        return $this->render('account/stokvel-suspended', [
            'title' => 'Stokvel suspended',
            'stokvels' => $suspended,
            'user' => $this->user(),
        ], 'layouts/guest');
    }
}
