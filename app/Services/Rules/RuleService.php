<?php

declare(strict_types=1);

namespace App\Services\Rules;

use App\Core\Database;
use App\Exceptions\TenantException;
use App\Services\AuditService;

final class RuleService
{
    public function __construct(
        private readonly Database $db,
        private readonly ?AuditService $audit = null,
    ) {
    }

    public function list(int $stokvelId): array
    {
        return $this->db->fetchAll(
            'SELECT r.*, (
                SELECT rv.version_number FROM rule_versions rv
                WHERE rv.rule_id = r.id AND rv.stokvel_id = r.stokvel_id
                ORDER BY rv.version_number DESC LIMIT 1
             ) AS current_version
             FROM rules r WHERE r.stokvel_id = :sid ORDER BY r.category, r.title',
            ['sid' => $stokvelId]
        );
    }

    public function create(int $stokvelId, int $userId, array $data): int
    {
        return $this->db->transaction(function () use ($stokvelId, $userId, $data) {
            $code = $this->nextCode($stokvelId, (string) $data['title']);
            $now = now_utc();
            $ruleId = (int) $this->db->insert(
                'INSERT INTO rules (stokvel_id, code, title, category, status, created_at, updated_at)
                 VALUES (:sid, :code, :title, :category, :status, :ca, :ua)',
                [
                    'sid' => $stokvelId,
                    'code' => $code,
                    'title' => $data['title'],
                    'category' => $data['category'] ?? 'general',
                    'status' => 'active',
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
            $this->db->insert(
                'INSERT INTO rule_versions
                    (stokvel_id, rule_id, version_number, content, effective_from, published_by_user_id, created_at)
                 VALUES
                    (:sid, :rule_id, 1, :content, :effective_from, :published_by, :created_at)',
                [
                    'sid' => $stokvelId,
                    'rule_id' => $ruleId,
                    'content' => $data['body'] ?? $data['content'] ?? '',
                    'effective_from' => $data['effective_from'] ?? date('Y-m-d'),
                    'published_by' => $userId,
                    'created_at' => $now,
                ]
            );
            $this->audit?->log(
                'rule.created',
                'rule',
                $ruleId,
                null,
                ['title' => $data['title']],
                ['user_id' => $userId],
                $stokvelId,
                $userId
            );
            return $ruleId;
        });
    }

    public function createNewVersion(int $stokvelId, int $ruleId, int $userId, string $body, ?string $effectiveFrom = null): int
    {
        $rule = $this->db->fetch(
            'SELECT * FROM rules WHERE stokvel_id = :sid AND id = :id LIMIT 1',
            ['sid' => $stokvelId, 'id' => $ruleId]
        );
        if (!$rule) {
            throw new TenantException('Rule not found.');
        }
        $latest = $this->db->fetch(
            'SELECT MAX(version_number) AS m FROM rule_versions WHERE stokvel_id = :sid AND rule_id = :rule_id',
            ['sid' => $stokvelId, 'rule_id' => $ruleId]
        );
        $next = ((int) ($latest['m'] ?? 0)) + 1;
        return (int) $this->db->insert(
            'INSERT INTO rule_versions
                (stokvel_id, rule_id, version_number, content, effective_from, published_by_user_id, created_at)
             VALUES
                (:sid, :rule_id, :version_number, :content, :effective_from, :published_by, :created_at)',
            [
                'sid' => $stokvelId,
                'rule_id' => $ruleId,
                'version_number' => $next,
                'content' => $body,
                'effective_from' => $effectiveFrom ?? date('Y-m-d'),
                'published_by' => $userId,
                'created_at' => now_utc(),
            ]
        );
    }

    public function activateVersion(int $stokvelId, int $versionId, int $userId): void
    {
        $version = $this->db->fetch(
            'SELECT * FROM rule_versions WHERE stokvel_id = :sid AND id = :id LIMIT 1',
            ['sid' => $stokvelId, 'id' => $versionId]
        );
        if (!$version) {
            throw new TenantException('Rule version not found.');
        }

        $this->db->transaction(function () use ($stokvelId, $version, $userId, $versionId) {
            $this->db->exec(
                'UPDATE rule_versions SET effective_to = :effective_to
                 WHERE stokvel_id = :sid AND rule_id = :rule_id AND id != :id AND effective_to IS NULL',
                [
                    'effective_to' => date('Y-m-d'),
                    'sid' => $stokvelId,
                    'rule_id' => $version['rule_id'],
                    'id' => $versionId,
                ]
            );
            $this->db->exec(
                'UPDATE rule_versions SET published_by_user_id = :uid WHERE stokvel_id = :sid AND id = :id',
                ['uid' => $userId, 'sid' => $stokvelId, 'id' => $versionId]
            );
            $this->db->exec(
                'UPDATE rules SET status = :status, updated_at = :ua WHERE stokvel_id = :sid AND id = :id',
                ['status' => 'active', 'ua' => now_utc(), 'sid' => $stokvelId, 'id' => $version['rule_id']]
            );
            $this->audit?->log(
                'rule.version_activated',
                'rule_version',
                $versionId,
                null,
                ['rule_id' => $version['rule_id'], 'version' => $version['version_number']],
                ['user_id' => $userId],
                $stokvelId,
                $userId
            );
        });
    }

    public function acknowledge(int $stokvelId, int $ruleVersionId, int $membershipId): void
    {
        $version = $this->db->fetch(
            'SELECT id FROM rule_versions WHERE stokvel_id = :sid AND id = :id LIMIT 1',
            ['sid' => $stokvelId, 'id' => $ruleVersionId]
        );
        if (!$version) {
            throw new TenantException('Rule version not found.');
        }
        $existing = $this->db->fetch(
            'SELECT id FROM rule_acknowledgements
             WHERE stokvel_id = :sid AND rule_version_id = :vid AND membership_id = :mid LIMIT 1',
            ['sid' => $stokvelId, 'vid' => $ruleVersionId, 'mid' => $membershipId]
        );
        if (!$existing) {
            $this->db->insert(
                'INSERT INTO rule_acknowledgements
                    (stokvel_id, rule_version_id, membership_id, acknowledged_at, created_at)
                 VALUES (:sid, :vid, :mid, :ack, :ca)',
                [
                    'sid' => $stokvelId,
                    'vid' => $ruleVersionId,
                    'mid' => $membershipId,
                    'ack' => now_utc(),
                    'ca' => now_utc(),
                ]
            );
        }
    }

    public function currentRulePack(int $stokvelId): array
    {
        return $this->db->fetchAll(
            'SELECT r.category, r.title, rv.version_number, rv.content AS body, rv.effective_from
             FROM rules r
             INNER JOIN rule_versions rv ON rv.rule_id = r.id AND rv.stokvel_id = r.stokvel_id
             WHERE r.stokvel_id = :sid AND r.status = :status
               AND (rv.effective_to IS NULL OR rv.effective_to >= CURDATE())
             ORDER BY rv.version_number DESC, r.category, r.title',
            ['sid' => $stokvelId, 'status' => 'active']
        );
    }

    private function nextCode(int $stokvelId, string $title): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $title) ?: 'RULE', 0, 10));
        $candidate = $base;
        $i = 0;
        while ($this->db->fetch(
            'SELECT id FROM rules WHERE stokvel_id = :sid AND code = :code LIMIT 1',
            ['sid' => $stokvelId, 'code' => $candidate]
        )) {
            $i++;
            $candidate = substr($base, 0, 8) . $i;
        }
        return $candidate;
    }
}
