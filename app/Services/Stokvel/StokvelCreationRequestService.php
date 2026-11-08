<?php

declare(strict_types=1);

namespace App\Services\Stokvel;

use App\Core\Database;
use App\Exceptions\HttpException;
use App\Repositories\StokvelRepository;
use App\Services\AuditService;
use App\Services\Auth\AuthService;
use App\Services\Notifications\NotificationService;
use App\Support\StokvelTypeCatalog;

/**
 * Guest (and authenticated) stokvel creation requests with platform approval + claim.
 */
final class StokvelCreationRequestService
{
    public const CLAIM_TTL_HOURS = 72;

    public function __construct(
        private readonly Database $db,
        private readonly StokvelRepository $stokvels,
        private readonly NotificationService $notifications,
        private readonly AuditService $audit,
    ) {
    }

    /**
     * @param array{first_name:string,last_name:string,email:string,stokvel_name:string,stokvel_type?:string,description?:string} $data
     * @return array{id:int,public_token:string,status:string}
     */
    public function submit(array $data): array
    {
        $email = strtolower(trim($data['email']));
        $name = trim($data['stokvel_name']);
        $first = trim($data['first_name']);
        $last = trim($data['last_name']);
        $type = trim((string) ($data['stokvel_type'] ?? 'general_savings'));
        if ($type === '' || !StokvelTypeCatalog::isValidCode($type, true, $this->db)) {
            $type = 'general_savings';
        }
        $description = trim((string) ($data['description'] ?? ''));

        if ($email === '' || $name === '' || $first === '' || $last === '') {
            throw new HttpException(422, 'Please complete all required fields.');
        }

        $duplicateKey = hash('sha256', $email . '|' . mb_strtolower($name));
        $existing = $this->db->fetch(
            "SELECT id, public_token, status FROM stokvel_creation_requests
             WHERE duplicate_key = :dk AND status IN ('pending', 'approved')
             ORDER BY id DESC LIMIT 1",
            ['dk' => $duplicateKey]
        );
        if ($existing) {
            return [
                'id' => (int) $existing['id'],
                'public_token' => (string) $existing['public_token'],
                'status' => (string) $existing['status'],
                'duplicate' => true,
            ];
        }

        $now = now_utc();
        $publicToken = bin2hex(random_bytes(32));
        $id = (int) $this->db->insert(
            'INSERT INTO stokvel_creation_requests (
                public_token, requester_first_name, requester_last_name, requester_email,
                stokvel_name, stokvel_type, description, status, duplicate_key, created_at, updated_at
             ) VALUES (
                :token, :fn, :ln, :email, :name, :type, :desc, :status, :dk, :ca, :ua
             )',
            [
                'token' => $publicToken,
                'fn' => $first,
                'ln' => $last,
                'email' => $email,
                'name' => $name,
                'type' => $type,
                'desc' => $description !== '' ? $description : null,
                'status' => 'pending',
                'dk' => $duplicateKey,
                'ca' => $now,
                'ua' => $now,
            ]
        );

        $this->audit->log('stokvel_request.submitted', 'stokvel_creation_request', $id, metadata: [
            'stokvel_name' => $name,
            'requester_email' => $email,
        ]);

        $this->notifyPlatformAdmins($id, $publicToken, $name, $first, $last, $email);

        return [
            'id' => $id,
            'public_token' => $publicToken,
            'status' => 'pending',
            'duplicate' => false,
        ];
    }

    /** @return array<string, mixed>|null */
    public function findByPublicToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 32) {
            return null;
        }
        return $this->db->fetch(
            'SELECT * FROM stokvel_creation_requests WHERE public_token = :t LIMIT 1',
            ['t' => $token]
        );
    }

    public function approve(int $requestId, int $adminUserId): array
    {
        return $this->db->transaction(function () use ($requestId, $adminUserId) {
            $row = $this->db->fetch(
                'SELECT * FROM stokvel_creation_requests WHERE id = :id LIMIT 1 FOR UPDATE',
                ['id' => $requestId]
            );
            if (!$row) {
                throw new HttpException(404, 'Request not found.');
            }
            if ((string) $row['status'] !== 'pending') {
                throw new HttpException(422, 'This request has already been processed.');
            }

            $slugBase = preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $row['stokvel_name'])) ?: 'stokvel';
            $slug = trim($slugBase, '-') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

            $stokvelId = $this->createStokvelRecord([
                'name' => (string) $row['stokvel_name'],
                'slug' => $slug,
                'stokvel_type' => (string) $row['stokvel_type'],
                'status' => 'active',
                'created_by_user_id' => null,
                'created_via' => 'guest_request',
                'creation_approved_by_user_id' => $adminUserId,
                'approval_bypassed' => 0,
            ]);

            $claimPlain = bin2hex(random_bytes(32));
            $claimHash = hash('sha256', $claimPlain);
            $expires = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify('+' . self::CLAIM_TTL_HOURS . ' hours')
                ->format('Y-m-d H:i:s');
            $now = now_utc();

            $this->db->exec(
                "UPDATE stokvel_creation_requests SET
                    status = 'approved', reviewed_by_user_id = :admin, reviewed_at = :ra,
                    stokvel_id = :sid, claim_token_hash = :ch, claim_expires_at = :exp, updated_at = :ua
                 WHERE id = :id AND status = 'pending'",
                [
                    'admin' => $adminUserId,
                    'ra' => $now,
                    'sid' => $stokvelId,
                    'ch' => $claimHash,
                    'exp' => $expires,
                    'ua' => $now,
                    'id' => $requestId,
                ]
            );

            $this->audit->log('stokvel_request.approved', 'stokvel_creation_request', $requestId, metadata: [
                'stokvel_id' => $stokvelId,
                'admin_user_id' => $adminUserId,
            ], stokvelId: $stokvelId, userId: $adminUserId);

            $email = strtolower((string) $row['requester_email']);
            $existingUser = $this->db->fetch('SELECT id FROM users WHERE email = :e LIMIT 1', ['e' => $email]);

            // Existing account: assign chairperson immediately and email a login/manage link.
            if ($existingUser) {
                $userId = (int) $existingUser['id'];
                $membershipId = $this->ensureChairpersonMembership($stokvelId, $userId);
                $this->db->exec(
                    "UPDATE stokvel_creation_requests SET
                        status = 'claimed', claimed_at = :ca, claimed_user_id = :uid,
                        claim_token_hash = NULL, updated_at = :ua
                     WHERE id = :id AND status = 'approved'",
                    ['ca' => $now, 'uid' => $userId, 'ua' => $now, 'id' => $requestId]
                );
                $this->db->exec(
                    'UPDATE stokvels SET created_by_user_id = COALESCE(created_by_user_id, :uid), updated_at = :ua WHERE id = :id',
                    ['uid' => $userId, 'ua' => $now, 'id' => $stokvelId]
                );
                $this->audit->log('stokvel_request.claimed', 'stokvel_creation_request', $requestId, metadata: [
                    'stokvel_id' => $stokvelId,
                    'membership_id' => $membershipId,
                    'created_user' => false,
                    'auto_assigned_on_approve' => true,
                ], stokvelId: $stokvelId, userId: $userId);

                $loginUrl = url('/login');
                $manageUrl = url('/app');
                $body = "Your stokvel creation request for \"{$row['stokvel_name']}\" was approved.\n\n"
                    . "You have been assigned as chairperson with full management permissions.\n\n"
                    . "Sign in to manage your stokvel:\n{$loginUrl}\n\n"
                    . "After signing in, open your dashboard:\n{$manageUrl}\n";
                $this->notifications->sendMailNow(
                    $email,
                    'Your stokvel was approved — sign in to manage it',
                    $body,
                    ['type' => 'stokvel_approved_login', 'request_id' => $requestId, 'stokvel_id' => $stokvelId]
                );

                return [
                    'request_id' => $requestId,
                    'stokvel_id' => $stokvelId,
                    'claim_token' => null,
                    'auto_claimed' => true,
                    'user_id' => $userId,
                ];
            }

            $claimUrl = url('/stokvel-claims/' . $claimPlain);
            $expiresLocal = sa_datetime_email($expires);
            $body = "Your stokvel creation request for \"{$row['stokvel_name']}\" was approved.\n\n"
                . "Use this secure link to create your chairperson account and manage the stokvel:\n"
                . $claimUrl . "\n\n"
                . "This link expires at {$expiresLocal} (" . self::CLAIM_TTL_HOURS . " hours).\n\n"
                . "If you did not request this, ignore this email.";
            $this->notifications->sendMailNow(
                $email,
                'Your stokvel was approved — claim your account',
                $body,
                ['type' => 'stokvel_claim', 'request_id' => $requestId]
            );

            return [
                'request_id' => $requestId,
                'stokvel_id' => $stokvelId,
                'claim_token' => $claimPlain,
                'auto_claimed' => false,
            ];
        });
    }

    /**
     * Logged-in requester with matching email claims an approved stokvel (no password prompt).
     *
     * @return array{user_id:int,stokvel_id:int,created_user:bool}|null
     */
    public function claimApprovedForAuthenticatedUser(int $userId, string $email): ?array
    {
        $email = strtolower(trim($email));
        if ($userId < 1 || $email === '') {
            return null;
        }

        return $this->db->transaction(function () use ($userId, $email) {
            $row = $this->db->fetch(
                "SELECT * FROM stokvel_creation_requests
                 WHERE requester_email = :e AND status = 'approved'
                 ORDER BY id DESC LIMIT 1 FOR UPDATE",
                ['e' => $email]
            );
            if (!$row) {
                return null;
            }

            $expires = (string) ($row['claim_expires_at'] ?? '');
            if ($expires !== '' && $expires < now_utc()) {
                $this->db->exec(
                    "UPDATE stokvel_creation_requests SET status = 'expired', updated_at = :ua WHERE id = :id",
                    ['ua' => now_utc(), 'id' => (int) $row['id']]
                );
                throw new HttpException(422, 'Your approval claim window has expired. Contact support or submit a new request.');
            }

            $user = $this->db->fetch('SELECT id, email FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
            if (!$user || strtolower((string) $user['email']) !== $email) {
                throw new HttpException(403, 'You can only claim a stokvel requested with your account email.');
            }

            $stokvelId = (int) ($row['stokvel_id'] ?? 0);
            if ($stokvelId < 1) {
                throw new HttpException(500, 'Approved request is missing a stokvel.');
            }

            $membershipId = $this->ensureChairpersonMembership($stokvelId, $userId);
            $now = now_utc();
            $this->db->exec(
                "UPDATE stokvel_creation_requests SET
                    status = 'claimed', claimed_at = :ca, claimed_user_id = :uid,
                    claim_token_hash = NULL, updated_at = :ua
                 WHERE id = :id AND status = 'approved'",
                ['ca' => $now, 'uid' => $userId, 'ua' => $now, 'id' => (int) $row['id']]
            );
            $this->db->exec(
                'UPDATE stokvels SET created_by_user_id = COALESCE(created_by_user_id, :uid), updated_at = :ua WHERE id = :id',
                ['uid' => $userId, 'ua' => $now, 'id' => $stokvelId]
            );
            $this->audit->log('stokvel_request.claimed', 'stokvel_creation_request', (int) $row['id'], metadata: [
                'stokvel_id' => $stokvelId,
                'membership_id' => $membershipId,
                'created_user' => false,
                'claimed_from_get_started' => true,
            ], stokvelId: $stokvelId, userId: $userId);

            return [
                'user_id' => $userId,
                'stokvel_id' => $stokvelId,
                'created_user' => false,
            ];
        });
    }

    public function reject(int $requestId, int $adminUserId, string $reason = ''): void
    {
        $this->db->transaction(function () use ($requestId, $adminUserId, $reason) {
            $row = $this->db->fetch(
                'SELECT * FROM stokvel_creation_requests WHERE id = :id LIMIT 1 FOR UPDATE',
                ['id' => $requestId]
            );
            if (!$row) {
                throw new HttpException(404, 'Request not found.');
            }
            if ((string) $row['status'] !== 'pending') {
                throw new HttpException(422, 'This request has already been processed.');
            }
            $now = now_utc();
            $this->db->exec(
                "UPDATE stokvel_creation_requests SET
                    status = 'rejected', rejection_reason = :reason,
                    reviewed_by_user_id = :admin, reviewed_at = :ra, updated_at = :ua
                 WHERE id = :id AND status = 'pending'",
                [
                    'reason' => $reason !== '' ? $reason : null,
                    'admin' => $adminUserId,
                    'ra' => $now,
                    'ua' => $now,
                    'id' => $requestId,
                ]
            );
            $this->audit->log('stokvel_request.rejected', 'stokvel_creation_request', $requestId, metadata: [
                'admin_user_id' => $adminUserId,
            ], userId: $adminUserId);

            $body = "Your stokvel creation request for \"{$row['stokvel_name']}\" was not approved."
                . ($reason !== '' ? "\n\nReason: {$reason}" : '')
                . "\n\nYou may submit a new request with updated details if appropriate.";
            $this->notifications->sendMailNow(
                (string) $row['requester_email'],
                'Stokvel creation request update',
                $body,
                ['type' => 'stokvel_request_rejected', 'request_id' => $requestId]
            );
        });
    }

    /**
     * Platform admin creates a stokvel directly (approval bypassed).
     *
     * @param array{name:string,stokvel_type?:string,description?:string,chairperson_email?:string} $data
     */
    public function createDirectByAdmin(array $data, int $adminUserId): int
    {
        $name = trim($data['name']);
        if ($name === '') {
            throw new HttpException(422, 'Stokvel name is required.');
        }
        $type = trim((string) ($data['stokvel_type'] ?? 'general_savings'));
        if ($type === '' || !StokvelTypeCatalog::isValidCode($type, true, $this->db)) {
            $type = 'general_savings';
        }
        $slugBase = preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?: 'stokvel';
        $slug = trim($slugBase, '-') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

        $stokvelId = $this->createStokvelRecord([
            'name' => $name,
            'slug' => $slug,
            'stokvel_type' => $type,
            'status' => 'active',
            'created_by_user_id' => $adminUserId,
            'created_via' => 'platform_admin',
            'creation_approved_by_user_id' => $adminUserId,
            'approval_bypassed' => 1,
        ]);

        $this->audit->log('stokvel.created_by_platform', 'stokvel', $stokvelId, metadata: [
            'approval_bypassed' => true,
            'admin_user_id' => $adminUserId,
        ], stokvelId: $stokvelId, userId: $adminUserId);

        return $stokvelId;
    }

    /**
     * Claim an approved stokvel: create/login user as chairperson.
     *
     * @return array{user_id:int,stokvel_id:int,created_user:bool}
     */
    public function claim(string $plainToken, array $accountData): array
    {
        $hash = hash('sha256', $plainToken);
        return $this->db->transaction(function () use ($hash, $accountData) {
            $row = $this->db->fetch(
                'SELECT * FROM stokvel_creation_requests WHERE claim_token_hash = :h LIMIT 1 FOR UPDATE',
                ['h' => $hash]
            );
            if (!$row) {
                throw new HttpException(404, 'Claim link is invalid.');
            }
            if ((string) $row['status'] === 'claimed') {
                throw new HttpException(422, 'This claim link has already been used.');
            }
            if ((string) $row['status'] !== 'approved') {
                throw new HttpException(422, 'This request is not available to claim.');
            }
            $expires = (string) ($row['claim_expires_at'] ?? '');
            if ($expires === '' || $expires < now_utc()) {
                $this->db->exec(
                    "UPDATE stokvel_creation_requests SET status = 'expired', updated_at = :ua WHERE id = :id",
                    ['ua' => now_utc(), 'id' => (int) $row['id']]
                );
                throw new HttpException(422, 'This claim link has expired. Contact support or submit a new request.');
            }

            $stokvelId = (int) ($row['stokvel_id'] ?? 0);
            if ($stokvelId < 1) {
                throw new HttpException(500, 'Approved request is missing a stokvel.');
            }

            $email = strtolower((string) $row['requester_email']);
            $existing = $this->db->fetch('SELECT * FROM users WHERE email = :e LIMIT 1', ['e' => $email]);
            $createdUser = false;
            if ($existing) {
                $userId = (int) $existing['id'];
                // Require password confirmation when claiming into an existing account.
                $password = (string) ($accountData['password'] ?? '');
                if ($password === '' || !password_verify($password, (string) ($existing['password_hash'] ?? ''))) {
                    throw new HttpException(422, 'An account already exists for this email. Enter your current password to claim.');
                }
            } else {
                $password = (string) ($accountData['password'] ?? '');
                $confirm = (string) ($accountData['password_confirmation'] ?? '');
                if ($password !== $confirm) {
                    throw new HttpException(422, 'Password confirmation does not match.');
                }
                app()->container()->get(\App\Services\Auth\PasswordPolicyService::class)->assertValid($password);
                /** @var AuthService $auth */
                $auth = app()->container()->get(AuthService::class);
                $user = $auth->register([
                    'first_name' => (string) $row['requester_first_name'],
                    'last_name' => (string) $row['requester_last_name'],
                    'email' => $email,
                    'mobile' => (string) ($accountData['mobile'] ?? ''),
                    'password' => $password,
                ]);
                $userId = (int) $user['id'];
                $createdUser = true;
            }

            $membershipId = $this->ensureChairpersonMembership($stokvelId, $userId);
            $now = now_utc();
            $this->db->exec(
                "UPDATE stokvel_creation_requests SET
                    status = 'claimed', claimed_at = :ca, claimed_user_id = :uid,
                    claim_token_hash = NULL, updated_at = :ua
                 WHERE id = :id AND status = 'approved'",
                ['ca' => $now, 'uid' => $userId, 'ua' => $now, 'id' => (int) $row['id']]
            );

            $this->db->exec(
                'UPDATE stokvels SET created_by_user_id = COALESCE(created_by_user_id, :uid), updated_at = :ua WHERE id = :id',
                ['uid' => $userId, 'ua' => $now, 'id' => $stokvelId]
            );

            $this->audit->log('stokvel_request.claimed', 'stokvel_creation_request', (int) $row['id'], metadata: [
                'stokvel_id' => $stokvelId,
                'membership_id' => $membershipId,
                'created_user' => $createdUser,
            ], stokvelId: $stokvelId, userId: $userId);

            return [
                'user_id' => $userId,
                'stokvel_id' => $stokvelId,
                'created_user' => $createdUser,
            ];
        });
    }

    /** @param array<string, mixed> $data */
    private function createStokvelRecord(array $data): int
    {
        $now = now_utc();
        try {
            return (int) $this->db->insert(
                'INSERT INTO stokvels (
                    uuid, name, slug, stokvel_type, status, timezone, currency_code,
                    created_by_user_id, created_via, creation_approved_by_user_id, approval_bypassed,
                    activated_at, created_at, updated_at
                 ) VALUES (
                    :uuid, :name, :slug, :stokvel_type, :status, :timezone, :currency_code,
                    :created_by_user_id, :created_via, :approved_by, :bypassed,
                    :activated_at, :created_at, :updated_at
                 )',
                [
                    'uuid' => uuid(),
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'stokvel_type' => $data['stokvel_type'] ?? 'general_savings',
                    'status' => $data['status'] ?? 'active',
                    'timezone' => env('APP_TIMEZONE', 'Africa/Johannesburg'),
                    'currency_code' => env('APP_CURRENCY', 'ZAR'),
                    'created_by_user_id' => $data['created_by_user_id'] ?? null,
                    'created_via' => $data['created_via'] ?? 'platform_admin',
                    'approved_by' => $data['creation_approved_by_user_id'] ?? null,
                    'bypassed' => (int) ($data['approval_bypassed'] ?? 0),
                    'activated_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        } catch (\Throwable) {
            // Pre-migration fallback without provenance columns.
            return $this->stokvels->create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'stokvel_type' => $data['stokvel_type'] ?? 'general_savings',
                'status' => $data['status'] ?? 'active',
                'created_by_user_id' => $data['created_by_user_id'] ?? null,
            ]);
        }
    }

    private function ensureChairpersonMembership(int $stokvelId, int $userId): int
    {
        $existing = $this->db->fetch(
            'SELECT id FROM memberships WHERE stokvel_id = :sid AND user_id = :uid LIMIT 1',
            ['sid' => $stokvelId, 'uid' => $userId]
        );
        $now = now_utc();
        if ($existing) {
            $membershipId = (int) $existing['id'];
            $this->db->exec(
                "UPDATE memberships SET status = 'active', updated_at = :ua WHERE id = :id",
                ['ua' => $now, 'id' => $membershipId]
            );
        } else {
            $membershipId = (int) $this->db->insert(
                'INSERT INTO memberships (stokvel_id, user_id, status, joined_at, created_at, updated_at)
                 VALUES (:sid, :uid, :status, :ja, :ca, :ua)',
                [
                    'sid' => $stokvelId,
                    'uid' => $userId,
                    'status' => 'active',
                    'ja' => $now,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
        }

        $role = $this->db->fetch(
            "SELECT id FROM roles WHERE code = 'chairperson' AND (stokvel_id IS NULL OR stokvel_id = :sid) ORDER BY stokvel_id DESC LIMIT 1",
            ['sid' => $stokvelId]
        );
        if ($role) {
            $has = $this->db->fetch(
                'SELECT id FROM membership_roles WHERE membership_id = :mid AND role_id = :rid LIMIT 1',
                ['mid' => $membershipId, 'rid' => (int) $role['id']]
            );
            if (!$has) {
                try {
                    $this->db->insert(
                        'INSERT INTO membership_roles (membership_id, role_id, assigned_at) VALUES (:mid, :rid, :aa)',
                        ['mid' => $membershipId, 'rid' => (int) $role['id'], 'aa' => $now]
                    );
                } catch (\Throwable) {
                    $this->db->insert(
                        'INSERT INTO membership_roles (membership_id, role_id, created_at) VALUES (:mid, :rid, :ca)',
                        ['mid' => $membershipId, 'rid' => (int) $role['id'], 'ca' => $now]
                    );
                }
            }
        }

        return $membershipId;
    }

    private function notifyPlatformAdmins(
        int $requestId,
        string $publicToken,
        string $stokvelName,
        string $first,
        string $last,
        string $email
    ): void {
        $admins = $this->db->fetchAll(
            "SELECT u.id, u.email FROM users u
             WHERE u.status = 'active'
               AND u.platform_role IN ('platform_super_admin','super_admin','platform_support','support')
               AND u.email IS NOT NULL AND u.email <> ''"
        );
        $reviewUrl = url('/platform/stokvel-requests/' . $requestId);
        $statusUrl = url('/stokvel-requests/' . $publicToken);
        $subject = 'New stokvel creation request: ' . $stokvelName;
        $body = "A guest submitted a stokvel creation request.\n\n"
            . "Stokvel: {$stokvelName}\n"
            . "Requester: {$first} {$last} <{$email}>\n\n"
            . "Review and approve/reject:\n{$reviewUrl}\n\n"
            . "Public status page (for requester):\n{$statusUrl}\n";

        foreach ($admins as $admin) {
            try {
                $this->notifications->sendMailNow(
                    (string) $admin['email'],
                    $subject,
                    $body,
                    ['type' => 'stokvel_request_review', 'request_id' => $requestId]
                );
            } catch (\Throwable) {
                // Continue notifying remaining admins.
            }
        }
        if ($admins === []) {
            // Fallback log so operators notice missing platform admins.
            try {
                app()->container()->get(\App\Core\Logger::class)->warning(
                    'stokvel creation request submitted but no platform admins found to email',
                    ['request_id' => $requestId]
                );
            } catch (\Throwable) {
            }
        }
    }
}
