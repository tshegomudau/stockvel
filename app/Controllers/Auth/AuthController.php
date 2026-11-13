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
        // Fresh login: multi-stokvel users must pick which tenant to open.
        Session::forget('active_stokvel_id');
        Session::forget('stokvel_id');
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
        $home = home_path_for_user($user);
        if ($home === '/account/select-stokvel') {
            return $this->redirect($home);
        }
        $intended = Session::get('intended_url') ?? Session::getFlash('intended_url');
        Session::forget('intended_url');
        if (is_string($intended) && $intended !== '' && str_starts_with($intended, '/')) {
            return $this->redirect($intended);
        }
        return $this->redirect($home);
    }

    public function showRegister(Request $request): Response
    {
        if ($this->userId() !== null) {
            return $this->redirect(home_path_for_user($this->user()));
        }
        return $this->render('auth/register', [
            'title' => 'Create account',
            'passwordPolicy' => $this->passwordPolicyConfig(),
        ], 'layouts/guest');
    }

    public function register(Request $request): Response
    {
        $intent = trim((string) $request->input('intent', 'create_stokvel'));
        if (!in_array($intent, ['create_stokvel', 'join_stokvel'], true)) {
            $intent = 'create_stokvel';
        }
        /** @var \App\Services\Auth\PasswordPolicyService $policy */
        $policy = app()->container()->get(\App\Services\Auth\PasswordPolicyService::class);
        $min = (int) $policy->rules()['min_length'];
        $data = [
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'email' => trim((string) $request->input('email', '')),
            'mobile' => trim((string) $request->input('mobile', '')),
            'password' => (string) $request->input('password', ''),
            'password_confirmation' => (string) $request->input('password_confirmation', ''),
            'consent' => (bool) $request->input('consent'),
            'intent' => $intent,
        ];
        $validator = Validator::make($data, [
            'first_name' => 'required|min:2',
            'last_name' => 'required|min:2',
            'email' => 'required|email',
            'password' => 'required|min:' . $min . '|confirmed',
            'consent' => 'required',
        ]);
        $passwordErrors = $policy->validate($data['password']);
        if ($validator->fails() || $passwordErrors !== []) {
            $this->flashOld($request);
            $errors = $validator->errors();
            if ($passwordErrors !== []) {
                $errors['password'] = array_merge($errors['password'] ?? [], $passwordErrors);
            }
            $this->flashErrors($errors);
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
            // Do not confirm whether the email already exists.
            Session::flash('error', 'Could not create your account. If you already registered, try signing in.');
            return $this->redirect('/register');
        }

        Session::regenerate(true);
        Session::set('user_id', $userId);
        Session::set('registration_intent', $intent);
        $intended = Session::get('intended_url');
        if (is_string($intended) && str_starts_with($intended, '/invitations/')) {
            Session::flash('success', 'Account created. Accept your invitation to continue.');
            return $this->redirect($intended);
        }
        if ($intent === 'create_stokvel') {
            Session::flash('success', 'Account created. Continue with your stokvel request.');
            return $this->redirect('/start-stokvel');
        }
        Session::flash('success', 'Account created. A committee member must invite this email before you can join a stokvel.');
        return $this->redirect('/account/get-started');
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
        $email = strtolower(trim((string) $request->input('email', '')));
        // Always show the same response — do not reveal whether the email exists.
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            try {
                /** @var \App\Services\Auth\PasswordResetService $reset */
                $reset = app()->container()->get(\App\Services\Auth\PasswordResetService::class);
                $reset->sendResetLink($email);
            } catch (\Throwable) {
                // Swallow delivery failures for the public response; check jobs / mail logs.
            }
        }

        Session::flash('success', 'If that email exists, we sent reset instructions.');
        return $this->redirect('/login');
    }

    public function showReset(Request $request, string $token): Response
    {
        return $this->render('auth/reset', [
            'title' => 'Reset password',
            'token' => $token,
            'passwordPolicy' => $this->passwordPolicyConfig(),
        ], 'layouts/guest');
    }

    public function reset(Request $request, string $token): Response
    {
        $password = (string) $request->input('password', '');
        $confirm = (string) $request->input('password_confirmation', '');
        /** @var \App\Services\Auth\PasswordPolicyService $policy */
        $policy = app()->container()->get(\App\Services\Auth\PasswordPolicyService::class);
        if ($password !== $confirm) {
            Session::flash('error', 'Passwords must match.');
            return $this->redirect('/reset-password/' . urlencode($token));
        }
        $passwordErrors = $policy->validate($password);
        if ($passwordErrors !== []) {
            Session::flash('error', $passwordErrors[0]);
            return $this->redirect('/reset-password/' . urlencode($token));
        }

        /** @var \App\Services\Auth\PasswordResetService $reset */
        $reset = app()->container()->get(\App\Services\Auth\PasswordResetService::class);
        $ok = $reset->reset($token, $password);

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

    /** @return array<string, mixed> */
    private function passwordPolicyConfig(): array
    {
        try {
            return app()->container()->get(\App\Services\Auth\PasswordPolicyService::class)->clientConfig();
        } catch (\Throwable) {
            return [
                'min_length' => 8,
                'require_upper' => true,
                'require_lower' => true,
                'require_number' => true,
                'require_special' => false,
                'labels' => [
                    'At least 8 characters',
                    'At least one uppercase letter (A–Z)',
                    'At least one lowercase letter (a–z)',
                    'At least one number (0–9)',
                ],
            ];
        }
    }
}
