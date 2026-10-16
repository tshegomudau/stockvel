<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;
use App\Core\TenantContext;
use App\Exceptions\HttpException;
use App\Repositories\UserRepository;
use App\Services\AuditService;
use App\Services\PermissionService;

final class AuthService
{
    public function __construct(
        private readonly Database $db,
        private readonly UserRepository $users,
        private readonly RememberTokenService $rememberTokens,
        private readonly TenantContext $tenant,
        private readonly PermissionService $permissions,
        private readonly AuditService $audit,
        private readonly Logger $logger
    ) {
    }

    public static function hashPassword(string $password): string
    {
        $algo = strtolower((string) env('PASSWORD_ALGO', 'argon2id'));
        if ($algo === 'argon2id' && defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 1,
            ]);
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function register(array $data): array
    {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        if ($email === '' || $password === '') {
            throw new HttpException(422, 'Email and password are required.');
        }
        if ($this->users->findByEmail($email)) {
            throw new HttpException(422, 'An account with this email already exists.');
        }

        $userId = $this->users->create([
            'email' => $email,
            'password_hash' => self::hashPassword($password),
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'mobile' => trim((string) ($data['mobile'] ?? ($data['phone'] ?? ''))),
            'status' => 'active',
        ]);

        $user = $this->users->findById($userId);
        if (!$user) {
            throw new HttpException(500, 'Registration failed.');
        }

        $this->audit->log('user.registered', 'user', $userId, metadata: ['user_id' => $userId]);

        return $this->sanitizeUser($user);
    }

    public function registerLegacy(string $email, string $password, string $firstName, string $lastName): int
    {
        $user = $this->register([
            'email' => $email,
            'password' => $password,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
        return (int) $user['id'];
    }

    public function attempt(string $email, string $password, bool $remember = false): ?array
    {
        try {
            return $this->login($email, $password, $remember);
        } catch (HttpException $e) {
            if (in_array($e->getStatus(), [401, 423], true)) {
                return null;
            }
            throw $e;
        }
    }

    public function login(string $email, string $password, bool $remember = false): array
    {
        $email = strtolower(trim($email));
        $user = $this->users->findByEmail($email);
        if (!$user) {
            $this->logger->security('Failed login (unknown email)', ['email' => $email]);
            throw new HttpException(401, 'Invalid credentials.');
        }

        $userId = (int) $user['id'];
        if ($this->isLocked($user)) {
            throw new HttpException(423, 'Account temporarily locked. Try again later.');
        }

        if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            $this->registerFailedAttempt($userId, $email);
            throw new HttpException(401, 'Invalid credentials.');
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID)) {
            $this->users->updatePassword($userId, self::hashPassword($password));
            $user = $this->users->findById($userId) ?? $user;
        }

        $this->users->resetLoginAttempts($userId);
        $this->establishSession($user);
        if ($remember) {
            $this->rememberTokens->create($userId);
        }

        $this->recordLoginAttempt($email, true);
        $this->audit->log('auth.login', 'user', $userId, metadata: ['user_id' => $userId]);

        return $this->sanitizeUser($user);
    }

    public function logout(): void
    {
        $userId = $this->id();
        if ($userId !== null) {
            $this->rememberTokens->revokeForUser($userId);
            $this->audit->log('auth.logout', 'user', $userId, metadata: ['user_id' => $userId]);
        }

        Session::forget('active_stokvel_id');
        Session::forget('stokvel_id');
        Session::forget('user_id');
        Session::regenerate(true);
        $this->tenant->setUser(null);
        $this->tenant->setTenant(null);
    }

    public function attemptRememberMe(): ?array
    {
        $userId = $this->rememberTokens->consumeFromCookie();
        if ($userId === null) {
            return null;
        }

        $user = $this->users->findById($userId);
        if (!$user) {
            return null;
        }

        $this->establishSession($user);
        return $this->sanitizeUser($user);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function id(): ?int
    {
        $id = Session::get('user_id');
        return is_numeric($id) ? (int) $id : null;
    }

    public function user(): ?array
    {
        $id = $this->id();
        if ($id === null) {
            return null;
        }

        $user = $this->users->findById($id);
        if (!$user) {
            Session::forget('user_id');
            return null;
        }

        return $this->sanitizeUser($user);
    }

    public function establishSession(array $user): void
    {
        Session::regenerate(true);
        Session::set('user_id', (int) $user['id']);
        $sanitized = $this->sanitizeUser($user);
        $this->tenant->setUser($sanitized);
    }

    public function hydrateTenantFromSession(): void
    {
        $userId = Session::get('user_id');
        if (!$userId) {
            $userId = $this->rememberTokens->consumeFromCookie();
            if ($userId !== null) {
                Session::set('user_id', $userId);
            }
        }

        if (!$userId) {
            $this->tenant->setUser(null);
            $this->tenant->setTenant(null);
            return;
        }

        $user = $this->users->findById((int) $userId);
        if (!$user) {
            Session::forget('user_id');
            return;
        }

        $this->tenant->setUser($this->sanitizeUser($user));
        $stokvelId = Session::get('active_stokvel_id') ?? Session::get('stokvel_id');
        if ($stokvelId) {
            try {
                $this->switchStokvel((int) $stokvelId, (int) $userId);
            } catch (HttpException) {
                Session::forget('active_stokvel_id');
                Session::forget('stokvel_id');
            }
        }
    }

    public function switchStokvel(int $stokvelId, int $userId): void
    {
        if ($this->tenant->isPlatformAdmin()) {
            $stokvel = $this->db->fetch(
                'SELECT * FROM stokvels WHERE id = :id LIMIT 1',
                ['id' => $stokvelId]
            );
            if ($stokvel) {
                Session::set('active_stokvel_id', $stokvelId);
                Session::set('stokvel_id', $stokvelId);
                $perms = $this->permissions->forPlatformRole((string) ($this->tenant->platformRole() ?? ''));
                $this->tenant->setTenant($stokvel, null, $perms);
            }
            return;
        }

        $membership = $this->db->fetch(
            'SELECT m.*, s.name, s.slug, s.status AS stokvel_status
             FROM memberships m
             INNER JOIN stokvels s ON s.id = m.stokvel_id
             WHERE m.stokvel_id = :sid AND m.user_id = :uid
             AND m.status IN (\'active\', \'suspended\')
             LIMIT 1',
            ['sid' => $stokvelId, 'uid' => $userId]
        );
        if (!$membership) {
            throw new HttpException(403, 'Not a member of this stokvel.');
        }

        $stokvel = [
            'id' => (int) $membership['stokvel_id'],
            'name' => $membership['name'],
            'slug' => $membership['slug'],
            'status' => $membership['stokvel_status'] ?? $membership['status'],
        ];

        $perms = $this->permissions->codesForMembership($membership);
        Session::set('active_stokvel_id', $stokvelId);
        Session::set('stokvel_id', $stokvelId);
        $this->tenant->setTenant($stokvel, $membership, $perms);
    }

    public function findUserByEmail(string $email): ?array
    {
        return $this->users->findByEmail($email);
    }

    /** @return list<array<string, mixed>> */
    public function membershipsForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT m.*, s.name AS stokvel_name, s.slug AS stokvel_slug
             FROM memberships m
             INNER JOIN stokvels s ON s.id = m.stokvel_id
             WHERE m.user_id = :uid AND m.status IN (\'active\', \'suspended\')
             ORDER BY s.name ASC',
            ['uid' => $userId]
        );
    }

    /** @return list<string> */
    public function defaultPermissions(string $role): array
    {
        return $this->permissions->forRole($role);
    }

    private function registerFailedAttempt(int $userId, string $email): void
    {
        $this->users->incrementLoginAttempts($userId);
        $this->recordLoginAttempt($email, false);
        $user = $this->users->findById($userId);
        if (!$user) {
            return;
        }

        $max = (int) env('LOGIN_MAX_ATTEMPTS', 5);
        $attempts = (int) ($user['failed_login_count'] ?? $user['failed_login_attempts'] ?? $user['login_attempts'] ?? 0);
        if ($attempts >= $max) {
            $minutes = (int) env('LOGIN_LOCKOUT_MINUTES', 15);
            $lockedUntil = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify("+{$minutes} minutes")
                ->format('Y-m-d H:i:s');
            $this->users->lockUntil($userId, $lockedUntil);
        }
    }

    private function recordLoginAttempt(string $email, bool $successful): void
    {
        try {
            $this->db->insert(
                'INSERT INTO login_attempts (email, ip_address, user_agent, successful, attempted_at)
                 VALUES (:email, :ip, :ua, :ok, :at)',
                [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 512) : null,
                    'ok' => $successful ? 1 : 0,
                    'at' => now_utc(),
                ]
            );
        } catch (\Throwable) {
            // Table may be absent in minimal installs.
        }
    }

    private function isLocked(array $user): bool
    {
        $lockedUntil = $user['locked_until'] ?? null;
        if (!is_string($lockedUntil) || $lockedUntil === '') {
            return false;
        }
        return strtotime($lockedUntil) > time();
    }

    /** @return array<string, mixed> */
    private function sanitizeUser(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }
}
