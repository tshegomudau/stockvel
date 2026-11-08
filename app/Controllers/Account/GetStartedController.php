<?php

declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Services\Auth\AuthService;
use App\Services\Stokvel\StokvelCreationRequestService;

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
        $user = $this->user() ?? [];
        $email = strtolower(trim((string) ($user['email'] ?? '')));

        /** @var AuthService $auth */
        $auth = app()->container()->get(AuthService::class);
        $memberships = $auth->membershipsForUser($userId);
        if ($memberships !== []) {
            return $this->redirect(home_path_for_user($user));
        }

        $requests = [];
        try {
            if ($email !== '') {
                $requests = $this->db()->fetchAll(
                    "SELECT id, public_token, stokvel_name, status, created_at, claim_expires_at, stokvel_id
                     FROM stokvel_creation_requests
                     WHERE requester_email = :e
                     ORDER BY created_at DESC LIMIT 10",
                    ['e' => $email]
                );
            }
        } catch (\Throwable) {
            $requests = [];
        }

        // Approved request for this account → assign chairperson and enter the stokvel.
        $approved = null;
        foreach ($requests as $req) {
            if ((string) ($req['status'] ?? '') === 'approved') {
                $approved = $req;
                break;
            }
        }
        if ($approved !== null) {
            try {
                /** @var StokvelCreationRequestService $service */
                $service = app()->container()->get(StokvelCreationRequestService::class);
                $claimed = $service->claimApprovedForAuthenticatedUser($userId, $email);
                if ($claimed !== null) {
                    $auth->switchStokvel((int) $claimed['stokvel_id'], $userId);
                    Session::flash('success', 'Your stokvel was approved. You are signed in as chairperson.');
                    return $this->redirect('/app');
                }
            } catch (HttpException $e) {
                Session::flash('error', $e->getMessage());
            } catch (\Throwable) {
                Session::flash('error', 'Could not open your approved stokvel. Try again or contact support.');
            }
        }

        $hasOpenRequest = false;
        foreach ($requests as $req) {
            if (in_array((string) ($req['status'] ?? ''), ['pending', 'approved'], true)) {
                $hasOpenRequest = true;
                break;
            }
        }

        return $this->render('account/get-started', [
            'title' => 'Get started',
            'requests' => $requests,
            'pendingRequests' => $requests, // backward-compatible alias
            'hasOpenRequest' => $hasOpenRequest,
            'user' => $user,
        ], 'layouts/guest');
    }
}
