<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Landing for authenticated users who do not yet belong to a stokvel.
 * Avoids TenantMiddleware 403 after registration.
 */
final class GetStartedController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireAuth();
        $userId = (int) $this->userId();

        /** @var \App\Services\Auth\AuthService $auth */
        $auth = app()->container()->get(\App\Services\Auth\AuthService::class);
        $memberships = $auth->membershipsForUser($userId);
        if ($memberships !== []) {
            return $this->redirect(home_path_for_user($this->user()));
        }

        $pendingRequests = [];
        try {
            $email = strtolower((string) ($this->user()['email'] ?? ''));
            if ($email !== '') {
                $pendingRequests = $this->db()->fetchAll(
                    "SELECT public_token, stokvel_name, status, created_at, claim_expires_at
                     FROM stokvel_creation_requests
                     WHERE requester_email = :e
                     ORDER BY created_at DESC LIMIT 10",
                    ['e' => $email]
                );
            }
        } catch (\Throwable) {
            $pendingRequests = [];
        }

        return $this->render('account/get-started', [
            'title' => 'Get started',
            'pendingRequests' => $pendingRequests,
            'user' => $this->user(),
        ], 'layouts/guest');
    }
}
