<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Services\Auth\AuthService;
use App\Services\Stokvel\StokvelCreationRequestService;

final class StokvelClaimController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $hash = hash('sha256', $token);
        $row = $this->db()->fetch(
            'SELECT * FROM stokvel_creation_requests WHERE claim_token_hash = :h LIMIT 1',
            ['h' => $hash]
        );
        if (!$row) {
            // May already be claimed — look up by nothing; show generic message.
            Session::flash('error', 'This claim link is invalid or has already been used.');
            return $this->redirect('/login');
        }
        if ((string) $row['status'] === 'approved'
            && !empty($row['claim_expires_at'])
            && (string) $row['claim_expires_at'] < now_utc()
        ) {
            $this->db()->exec(
                "UPDATE stokvel_creation_requests SET status = 'expired', updated_at = :ua WHERE id = :id",
                ['ua' => now_utc(), 'id' => (int) $row['id']]
            );
            Session::flash('error', 'This claim link has expired.');
            return $this->redirect('/stokvel-requests/' . (string) $row['public_token']);
        }

        $existingUser = $this->db()->fetch(
            'SELECT id FROM users WHERE email = :e LIMIT 1',
            ['e' => strtolower((string) $row['requester_email'])]
        );

        $passwordPolicy = [];
        try {
            $passwordPolicy = app()->container()->get(\App\Services\Auth\PasswordPolicyService::class)->clientConfig();
        } catch (\Throwable) {
            $passwordPolicy = [];
        }

        return $this->render('public/stokvel-claim', [
            'title' => 'Claim your stokvel',
            'token' => $token,
            'request' => $row,
            'existingAccount' => $existingUser !== null,
            'passwordPolicy' => $passwordPolicy,
        ], 'layouts/guest');
    }

    public function store(Request $request, string $token): Response
    {
        try {
            /** @var StokvelCreationRequestService $service */
            $service = app()->container()->get(StokvelCreationRequestService::class);
            $result = $service->claim($token, [
                'password' => (string) $request->input('password', ''),
                'password_confirmation' => (string) $request->input('password_confirmation', ''),
                'mobile' => trim((string) $request->input('mobile', '')),
            ]);

            $user = $this->db()->fetch('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $result['user_id']]);
            /** @var AuthService $auth */
            $auth = app()->container()->get(AuthService::class);
            if ($user) {
                $auth->establishSession($user);
                try {
                    $auth->switchStokvel((int) $result['stokvel_id'], (int) $result['user_id']);
                } catch (\Throwable) {
                    Session::set('active_stokvel_id', $result['stokvel_id']);
                    Session::set('stokvel_id', $result['stokvel_id']);
                }
            }
            Session::flash('success', 'Welcome! Your stokvel is ready.');
            $user = $this->user();
            return $this->redirect($user ? home_path_for_user($user) : '/app');
        } catch (HttpException $e) {
            Session::flash('error', $e->getMessage());
            return $this->redirect('/stokvel-claims/' . rawurlencode($token));
        }
    }
}
