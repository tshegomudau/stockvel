<?php

declare(strict_types=1);

namespace App\Repositories;

final class MembershipRepository extends BaseRepository
{
    protected string $table = 'memberships';
    protected bool $tenantScoped = true;

    public function findForUser(int $userId, ?int $stokvelId = null): ?array
    {
        $sql = 'SELECT * FROM memberships WHERE user_id = :user_id';
        $params = ['user_id' => $userId];
        if ($stokvelId !== null) {
            $sql .= ' AND stokvel_id = :stokvel_id';
            $params['stokvel_id'] = $stokvelId;
        }
        $sql .= ' LIMIT 1';
        return $this->db->fetch($sql, $this->tenantParams($params));
    }

    public function findById(int $id): ?array
    {
        return $this->find($id);
    }

    public function findByMemberNumber(string $memberNumber): ?array
    {
        $sql = 'SELECT * FROM memberships WHERE member_number = :member_number AND ' .
            $this->applyTenant() . ' LIMIT 1';
        return $this->db->fetch($sql, $this->tenantParams(['member_number' => $memberNumber]));
    }

    public function listForUser(int $userId): array
    {
        $sql = 'SELECT m.*, s.name AS stokvel_name, s.slug AS stokvel_slug
            FROM memberships m
            INNER JOIN stokvels s ON s.id = m.stokvel_id
            WHERE m.user_id = :user_id
            ORDER BY s.name ASC';
        return $this->db->fetchAll($sql, ['user_id' => $userId]);
    }

    public function listActiveForStokvel(int $limit = 500): array
    {
        $sql = 'SELECT m.*, u.email, p.first_name, p.last_name
            FROM memberships m
            INNER JOIN users u ON u.id = m.user_id
            LEFT JOIN user_profiles p ON p.user_id = u.id
            WHERE ' . $this->applyTenant('m') . '
            AND m.status IN (\'active\', \'suspended\')
            ORDER BY m.member_number ASC
            LIMIT ' . (int) $limit;
        return $this->db->fetchAll($sql, $this->tenantParams());
    }

    public function nextMemberNumber(): string
    {
        $sql = 'SELECT member_number FROM memberships WHERE ' . $this->applyTenant() .
            ' ORDER BY id DESC LIMIT 1';
        $last = $this->db->fetch($sql, $this->tenantParams());
        $seq = 1;
        if ($last && preg_match('/(\d+)$/', (string) ($last['member_number'] ?? ''), $m)) {
            $seq = (int) $m[1] + 1;
        }
        return sprintf('M-%05d', $seq);
    }

    public function insertStatusHistory(array $data): int
    {
        if (!isset($data['stokvel_id'])) {
            $data['stokvel_id'] = $this->stokvelId();
        }
        if (!isset($data['created_at'])) {
            $data['created_at'] = now_utc();
        }
        if (isset($data['changed_by']) && !isset($data['changed_by_user_id'])) {
            $data['changed_by_user_id'] = $data['changed_by'];
            unset($data['changed_by']);
        }

        $columns = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO membership_status_history (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        return (int) $this->db->insert($sql, $data);
    }

    public function statusHistory(int $membershipId, int $limit = 50): array
    {
        $sql = 'SELECT * FROM membership_status_history
            WHERE membership_id = :membership_id AND ' . $this->applyTenant() .
            ' ORDER BY id DESC LIMIT ' . (int) $limit;
        return $this->db->fetchAll($sql, $this->tenantParams(['membership_id' => $membershipId]));
    }

    public function findInvitationByTokenHash(string $tokenHash): ?array
    {
        $sql = 'SELECT * FROM invitations
            WHERE token_hash = :token_hash AND ' . $this->applyTenant() . ' LIMIT 1';
        return $this->db->fetch($sql, $this->tenantParams(['token_hash' => $tokenHash]));
    }

    public function createInvitation(array $data): int
    {
        if (!isset($data['stokvel_id'])) {
            $data['stokvel_id'] = $this->stokvelId();
        }
        if (!isset($data['created_at'])) {
            $data['created_at'] = now_utc();
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = now_utc();
        }
        if (isset($data['invited_by']) && !isset($data['invited_by_user_id'])) {
            $data['invited_by_user_id'] = $data['invited_by'];
            unset($data['invited_by']);
        }

        $columns = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO invitations (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        return (int) $this->db->insert($sql, $data);
    }

    public function updateInvitation(int $id, array $data): void
    {
        $data['updated_at'] = now_utc();
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "{$col} = :{$col}";
        }
        $params = $this->tenantParams(array_merge($data, ['id' => $id]));
        $sql = 'UPDATE invitations SET ' . implode(', ', $sets) .
            ' WHERE id = :id AND ' . $this->applyTenant();
        $this->db->exec($sql, $params);
    }
}
