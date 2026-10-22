<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth\AuthService;
use App\Services\Auth\MfaService;

final class MfaController extends Controller
{
    public function show(Request $request): Response
    {
        $userId = $this->pendingUserId();
        if ($userId === null) {
            return $this->redirect('/login');
        }

        /** @var MfaService $mfa */
        $mfa = app()->container()->get(MfaService::class);
        return $this->render('auth/mfa', [
            'title' => 'Two-factor authentication',
            'totpEnabled' => $mfa->isTotpEnabled($userId),
            'hasPasskeys' => $mfa->passkeyCount($userId) > 0,
        ], 'layouts/guest');
    }

    public function verifyTotp(Request $request): Response
    {
        $userId = $this->pendingUserId();
        if ($userId === null) {
            return $this->redirect('/login');
        }

        $code = (string) $request->input('code', '');
        /** @var MfaService $mfa */
        $mfa = app()->container()->get(MfaService::class);
        if (!$mfa->verifyTotpOrRecovery($userId, $code)) {
            Session::flash('error', 'Invalid authenticator or recovery code.');
            return $this->redirect('/login/mfa');
        }

        $path = $this->finalizeLogin($userId);
        if ($path === null) {
            return $this->redirect('/login');
        }
        return $this->redirect($path);
    }

    public function passkeyOptions(Request $request): Response
    {
        $userId = $this->pendingUserId();
        if ($userId === null) {
            return Response::json(['ok' => false, 'error' => 'Session expired.'], 401);
        }
        /** @var MfaService $mfa */
        $mfa = app()->container()->get(MfaService::class);
        if ($mfa->passkeyCount($userId) === 0) {
            return Response::json(['ok' => false, 'error' => 'No passkeys registered.'], 422);
        }
        return Response::json(['ok' => true, 'options' => $mfa->beginPasskeyAssertion($userId)]);
    }

    public function verifyPasskey(Request $request): Response
    {
        $userId = $this->pendingUserId();
        if ($userId === null) {
            return Response::json(['ok' => false, 'error' => 'Session expired.'], 401);
        }

        $payload = $this->jsonBody($request);
        /** @var MfaService $mfa */
        $mfa = app()->container()->get(MfaService::class);
        if (!$mfa->completePasskeyAssertion($userId, $payload)) {
            return Response::json(['ok' => false, 'error' => 'Passkey verification failed.'], 422);
        }

        $path = $this->finalizeLogin($userId);
        if ($path === null) {
            return Response::json(['ok' => false, 'error' => 'Account not found.'], 401);
        }
        return Response::json(['ok' => true, 'redirect' => url($path)]);
    }

    public function cancel(Request $request): Response
    {
        Session::forget('mfa_pending_user_id');
        Session::forget('mfa_pending_remember');
        Session::flash('success', 'Sign-in cancelled.');
        return $this->redirect('/login');
    }

    private function pendingUserId(): ?int
    {
        $id = Session::get('mfa_pending_user_id');
        return is_numeric($id) ? (int) $id : null;
    }

    /** Establish session after MFA; returns relative path to redirect, or null on failure. */
    private function finalizeLogin(int $userId): ?string
    {
        $user = $this->db()->fetch('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
        if ($user === null) {
            Session::forget('mfa_pending_user_id');
            Session::forget('mfa_pending_remember');
            Session::flash('error', 'Account not found.');
            return null;
        }

        /** @var AuthService $auth */
        $auth = app()->container()->get(AuthService::class);
        $auth->establishSession($user);

        $remember = (bool) Session::get('mfa_pending_remember');
        Session::forget('mfa_pending_user_id');
        Session::forget('mfa_pending_remember');

        if ($remember) {
            try {
                app()->container()->get(\App\Services\Auth\RememberTokenService::class)->create($userId);
            } catch (\Throwable) {
                // optional
            }
        }

        if (!empty($user['email_verified_at'])) {
            Session::flash('success', 'Welcome back, ' . ($user['first_name'] ?? 'member') . '.');
        }

        $intended = Session::getFlash('intended_url');
        if (is_string($intended) && $intended !== '') {
            return $intended;
        }
        return home_path_for_user($user);
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        $raw = $request->rawBody();
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $request->all();
    }
}
