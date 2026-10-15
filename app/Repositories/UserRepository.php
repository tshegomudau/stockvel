<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected bool $tenantScoped = false;

    public function findByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));
        $sql = 'SELECT u.*, p.first_name, p.last_name
            FROM users u
            LEFT JOIN user_profiles p ON p.user_id = u.id
            WHERE u.email = :email LIMIT 1';
        $user = $this->db->fetch($sql, ['email' => $email]);
        if ($user) {
            return $user;
        }

        $sql = 'SELECT * FROM users WHERE email = :email LIMIT 1';
        return $this->db->fetch($sql, ['email' => $email]);
    }

    public function findById(int $id): ?array
    {
        $sql = 'SELECT u.*, p.first_name, p.last_name
            FROM users u
            LEFT JOIN user_profiles p ON p.user_id = u.id
            WHERE u.id = :id LIMIT 1';
        $user = $this->db->fetch($sql, ['id' => $id]);
        if ($user) {
            return $user;
        }

        $sql = 'SELECT * FROM users WHERE id = :id LIMIT 1';
        return $this->db->fetch($sql, ['id' => $id]);
    }

    public function findByUuid(string $uuid): ?array
    {
        $sql = 'SELECT * FROM users WHERE uuid = :uuid LIMIT 1';
        return $this->db->fetch($sql, ['uuid' => $uuid]);
    }

    public function create(array $data): int
    {
        $now = now_utc();
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));

        try {
            $userId = (int) $this->db->insert(
                'INSERT INTO users (email, mobile, password_hash, platform_role, email_verified_at, status, created_at, updated_at)
                 VALUES (:email, :mobile, :password_hash, :platform_role, :email_verified_at, :status, :created_at, :updated_at)',
                [
                    'email' => strtolower(trim((string) $data['email'])),
                    'mobile' => ($data['mobile'] ?? null) ?: null,
                    'password_hash' => (string) $data['password_hash'],
                    'platform_role' => $data['platform_role'] ?? null,
                    'email_verified_at' => $data['email_verified_at'] ?? null,
                    'status' => $data['status'] ?? 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            if ($firstName !== '' || $lastName !== '') {
                $this->db->insert(
                    'INSERT INTO user_profiles (user_id, first_name, last_name, created_at, updated_at)
                     VALUES (:user_id, :first_name, :last_name, :created_at, :updated_at)',
                    [
                        'user_id' => $userId,
                        'first_name' => $firstName !== '' ? $firstName : 'Member',
                        'last_name' => $lastName !== '' ? $lastName : '',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            return $userId;
        } catch (\Throwable) {
            if (!isset($data['uuid'])) {
                $data['uuid'] = uuid();
            }
            if (isset($data['email'])) {
                $data['email'] = strtolower(trim((string) $data['email']));
            }
            unset($data['first_name'], $data['last_name'], $data['mobile']);
            return $this->insert($data);
        }
    }

    public function incrementLoginAttempts(int $userId): void
    {
        try {
            $sql = 'UPDATE users SET failed_login_count = failed_login_count + 1, updated_at = :updated_at WHERE id = :id';
            $this->db->exec($sql, ['id' => $userId, 'updated_at' => now_utc()]);
        } catch (\Throwable) {
            $sql = 'UPDATE users SET failed_login_attempts = failed_login_attempts + 1, updated_at = :updated_at WHERE id = :id';
            $this->db->exec($sql, ['id' => $userId, 'updated_at' => now_utc()]);
        }
    }

    public function resetLoginAttempts(int $userId): void
    {
        try {
            $sql = 'UPDATE users SET failed_login_count = 0, locked_until = NULL, last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id';
            $this->db->exec($sql, [
                'id' => $userId,
                'last_login_at' => now_utc(),
                'updated_at' => now_utc(),
            ]);
        } catch (\Throwable) {
            $sql = 'UPDATE users SET failed_login_attempts = 0, locked_until = NULL, updated_at = :updated_at WHERE id = :id';
            $this->db->exec($sql, ['id' => $userId, 'updated_at' => now_utc()]);
        }
    }

    public function lockUntil(int $userId, string $lockedUntil): void
    {
        $this->update($userId, ['locked_until' => $lockedUntil]);
    }

    public function markEmailVerified(int $userId): void
    {
        $this->update($userId, ['email_verified_at' => now_utc()]);
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        try {
            $this->update($userId, [
                'password_hash' => $passwordHash,
                'updated_at' => now_utc(),
                'must_change_password' => 0,
            ]);
        } catch (\Throwable) {
            $this->update($userId, [
                'password_hash' => $passwordHash,
                'updated_at' => now_utc(),
            ]);
        }
    }
}
