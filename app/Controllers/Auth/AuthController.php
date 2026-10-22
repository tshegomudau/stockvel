<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        if ($this->userId() !== null) {
            return $this->redirect(home_path_for_user($this->user()));
        }
        return $this->render('auth/login', ['title' => 'Sign in'], 'layouts/guest');
    }

    public function login(Request $request): Response
    {
        $data = [
            'email' => trim((string) $request->input('email', '')),
            'password' => (string) $request->input('password', ''),
            'remember' => (bool) $request->input('remember'),
        ];
        $validator = Validator::make($data, [
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            $this->flashErrors($validator->errors());
            Session::flash('error', 'Please check your sign-in details.');
            return $this->redirect('/login');
        }

        $user = $this->db()->fetch(
            'SELECT * FROM users WHERE email = :email LIMIT 1',
            ['email' => strtolower($data['email'])]
        );
        if (!$user || !password_verify($data['password'], (string) ($user['password_hash'] ?? $user['password'] ?? ''))) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid email or password.');
            return $this->redirect('/login');
        }

        $userId = (int) $user['id'];

        /** @var \App\Services\Auth\MfaService $mfa */
        $mfa = app()->container()->get(\App\Services\Auth\MfaService::class);
        if ($mfa->requiresMfa($userId)) {
            Session::regenerate(true);
            Session::forget('user_id');
            Session::set('mfa_pending_user_id', $userId);
            Session::set('mfa_pending_remember', $data['remember']);
            return $this->redirect('/login/mfa');
        }

        /** @var \App\Services\Auth\AuthService $auth */
        $auth = app()->container()->get(\App\Services\Auth\AuthService::class);
        $auth->establishSession($user);
        if ($data['remember']) {
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
            return $this->redirect($intended);
        }
        return $this->redirect(home_path_for_user($user));
    }

    public function showRegister(Request $request): Response
    {
        if ($this->userId() !== null) {
            return $this->redirect(home_path_for_user($this->user()));
        }
        return $this->render('auth/register', ['title' => 'Create account'], 'layouts/guest');
    }

    public function register(Request $request): Response
    {
        $data = [
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'email' => trim((string) $request->input('email', '')),
            'mobile' => trim((string) $request->input('mobile', '')),
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
            'consent' => (bool) $request->input('consent'),
        ];
        $validator = Validator::make($data, [
            'first_name' => 'required|min:2',
            'last_name' => 'required|min:2',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
            'consent' => 'required',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            $this->flashErrors($validator->errors());
            Session::flash('error', 'Could not create your account.');
            return $this->redirect('/register');
        }

        try {
            /** @var \App\Services\Auth\AuthService $auth */
            $auth = app()->container()->get(\App\Services\Auth\AuthService::class);
            $user = $auth->register($data);
            $userId = (int) $user['id'];
        } catch (\Throwable) {
            $this->flashOld($request);
            Session::flash('error', 'An account with this email may already exist.');
            return $this->redirect('/register');
        }

        Session::regenerate(true);
        Session::set('user_id', $userId);
        Session::flash('success', 'Account created. Please verify your email.');
        return $this->redirect('/verify-email/notice');
    }

    public function showLogout(Request $request): Response
    {
        if ($this->userId() === null) {
            return $this->redirect('/login');
        }

        return $this->render('auth/logout', [
            'title' => 'Sign out',
        ], 'layouts/guest');
    }

    public function logout(Request $request): Response
    {
        /** @var \App\Services\Auth\AuthService $auth */
        $auth = app()->container()->get(\App\Services\Auth\AuthService::class);
        $auth->logout();
        Session::flash('success', 'You have been signed out.');
        return $this->redirect('/login');
    }

    public function showForgot(Request $request): Response
    {
        return $this->render('auth/forgot', ['title' => 'Forgot password'], 'layouts/guest');
    }

    public function forgot(Request $request): Response
    {
        $email = trim((string) $request->input('email', ''));
        $this->service(\App\Services\Auth\PasswordResetService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function sendResetLink(string $email): void
            {
                $user = $this->db->fetch('SELECT id FROM users WHERE email = :email', ['email' => $email]);
                if (!$user) {
                    return;
                }
                $token = bin2hex(random_bytes(32));
                $this->db->insert(
                    'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) VALUES (:uid, :th, :exp, :ca)',
                    [
                        'uid' => $user['id'],
                        'th' => hash('sha256', $token),
                        'exp' => (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s'),
                        'ca' => now_utc(),
                    ]
                );
            }
        })->sendResetLink($email);

        Session::flash('success', 'If that email exists, we sent reset instructions.');
        return $this->redirect('/login');
    }

    public function showReset(Request $request, string $token): Response
    {
        return $this->render('auth/reset', ['title' => 'Reset password', 'token' => $token], 'layouts/guest');
    }

    public function reset(Request $request, string $token): Response
    {
        $password = (string) $request->input('password', '');
        $confirm = (string) $request->input('password_confirmation', '');
        if (strlen($password) < 8 || $password !== $confirm) {
            Session::flash('error', 'Passwords must match and be at least 8 characters.');
            return $this->redirect('/reset-password/' . urlencode($token));
        }

        $ok = $this->service(\App\Services\Auth\PasswordResetService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function reset(string $token, string $password): bool
            {
                $row = $this->db->fetch(
                    'SELECT user_id FROM password_reset_tokens WHERE token_hash = :th AND expires_at > UTC_TIMESTAMP() LIMIT 1',
                    ['th' => hash('sha256', $token)]
                );
                if (!$row) {
                    return false;
                }
                $hash = password_hash($password, PASSWORD_ARGON2ID);
                $this->db->exec('UPDATE users SET password_hash = :ph, updated_at = :ua WHERE id = :id', [
                    'ph' => $hash,
                    'ua' => now_utc(),
                    'id' => $row['user_id'],
                ]);
                $this->db->exec('DELETE FROM password_reset_tokens WHERE user_id = :id', ['id' => $row['user_id']]);
                return true;
            }
        })->reset($token, $password);

        if (!$ok) {
            Session::flash('error', 'This reset link is invalid or expired.');
            return $this->redirect('/forgot-password');
        }

        Session::flash('success', 'Password updated. You can sign in now.');
        return $this->redirect('/login');
    }

    public function verifyEmail(Request $request, string $token): Response
    {
        $verified = $this->service(\App\Services\Auth\EmailVerificationService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function verify(string $token): bool
            {
                $row = $this->db->fetch(
                    'SELECT user_id FROM email_verification_tokens WHERE token_hash = :th AND expires_at > UTC_TIMESTAMP() LIMIT 1',
                    ['th' => hash('sha256', $token)]
                );
                if (!$row) {
                    return false;
                }
                $this->db->exec('UPDATE users SET email_verified_at = :v, updated_at = :ua WHERE id = :id', [
                    'v' => now_utc(),
                    'ua' => now_utc(),
                    'id' => $row['user_id'],
                ]);
                return true;
            }
        })->verify($token);

        Session::flash($verified ? 'success' : 'error', $verified ? 'Email verified successfully.' : 'Verification link invalid or expired.');
        return $this->redirect('/login');
    }

    public function resendVerification(Request $request): Response
    {
        $this->requireAuth();
        $userId = $this->userId();
        $this->service(\App\Services\Auth\EmailVerificationService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function resend(int $userId): void
            {
                $token = bin2hex(random_bytes(32));
                $this->db->insert(
                    'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at, created_at) VALUES (:uid, :th, :exp, :ca)',
                    [
                        'uid' => $userId,
                        'th' => hash('sha256', $token),
                        'exp' => (new \DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s'),
                        'ca' => now_utc(),
                    ]
                );
            }
        })->resend((int) $userId);

        Session::flash('success', 'Verification email sent.');
        return $this->redirectBack($request, '/verify-email/notice');
    }

    public function verifyNotice(Request $request): Response
    {
        return $this->render('auth/verify-notice', ['title' => 'Verify your email'], 'layouts/guest');
    }
}
