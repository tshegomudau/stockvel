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
                WHERE rv.rule_id = r.id AND rv.stokvel_id = r.stokvel_id AND rv.status = ?
                ORDER BY rv.version_number DESC LIMIT 1
             ) AS current_version
             FROM rules r WHERE r.stokvel_id = ? ORDER BY r.category, r.title',
            ['active', $stokvelId]
        );
    }

    public function create(int $stokvelId, int $userId, array $data): int
    {
        return $this->db->transaction(function () use ($stokvelId, $userId, $data) {
            $ruleId = (int) $this->db->insert(
                'INSERT INTO rules (stokvel_id, category, title, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$stokvelId, $data['category'], $data['title'], 'draft', $userId, now_utc(), now_utc()]
            );
            $this->db->insert(
                'INSERT INTO rule_versions (stokvel_id, rule_id, version_number, body, effective_from, status, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $stokvelId,
                    $ruleId,
                    1,
                    $data['body'],
                    $data['effective_from'] ?? date('Y-m-d'),
                    'draft',
                    $userId,
                    now_utc(),
                ]
            );
            $this->audit?->log($stokvelId, $userId, 'rule.created', 'rule', $ruleId, null, ['title' => $data['title']]);
            return $ruleId;
        });
    }

    public function createNewVersion(int $stokvelId, int $ruleId, int $userId, string $body, ?string $effectiveFrom = null): int
    {
        $rule = $this->db->fetch('SELECT * FROM rules WHERE stokvel_id = ? AND id = ?', [$stokvelId, $ruleId]);
        if (!$rule) {
            throw new TenantException('Rule not found.');
        }
        $latest = $this->db->fetch(
            'SELECT MAX(version_number) AS m FROM rule_versions WHERE stokvel_id = ? AND rule_id = ?',
            [$stokvelId, $ruleId]
        );
        $next = ((int) ($latest['m'] ?? 0)) + 1;
        return (int) $this->db->insert(
            'INSERT INTO rule_versions (stokvel_id, rule_id, version_number, body, effective_from, status, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$stokvelId, $ruleId, $next, $body, $effectiveFrom ?? date('Y-m-d'), 'draft', $userId, now_utc()]
        );
    }

    public function activateVersion(int $stokvelId, int $versionId, int $userId): void
    {
        $version = $this->db->fetch(
            'SELECT * FROM rule_versions WHERE stokvel_id = ? AND id = ?',
            [$stokvelId, $versionId]
        );
        if (!$version) {
            throw new TenantException('Rule version not found.');
        }

        $this->db->transaction(function () use ($stokvelId, $version, $userId, $versionId) {
            // Supersede currently active version — never overwrite historical body
            $this->db->exec(
                'UPDATE rule_versions SET status = ?, superseded_at = ? WHERE stokvel_id = ? AND rule_id = ? AND status = ?',
                ['superseded', now_utc(), $stokvelId, $version['rule_id'], 'active']
            );
            $this->db->exec(
                'UPDATE rule_versions SET status = ?, activated_by = ?, activated_at = ? WHERE stokvel_id = ? AND id = ?',
                ['active', $userId, now_utc(), $stokvelId, $versionId]
            );
            $this->db->exec(
                'UPDATE rules SET status = ?, updated_at = ? WHERE stokvel_id = ? AND id = ?',
                ['active', now_utc(), $stokvelId, $version['rule_id']]
            );
            $this->audit?->log($stokvelId, $userId, 'rule.version_activated', 'rule_version', $versionId, null, [
                'rule_id' => $version['rule_id'],
                'version' => $version['version_number'],
            ]);
        });
    }

    public function acknowledge(int $stokvelId, int $ruleVersionId, int $membershipId): void
    {
        $version = $this->db->fetch(
            'SELECT id FROM rule_versions WHERE stokvel_id = ? AND id = ?',
            [$stokvelId, $ruleVersionId]
        );
        if (!$version) {
            throw new TenantException('Rule version not found.');
        }
        $existing = $this->db->fetch(
            'SELECT id FROM rule_acknowledgements WHERE stokvel_id = ? AND rule_version_id = ? AND membership_id = ?',
            [$stokvelId, $ruleVersionId, $membershipId]
        );
        if (!$existing) {
            $this->db->insert(
                'INSERT INTO rule_acknowledgements (stokvel_id, rule_version_id, membership_id, acknowledged_at, created_at)
                 VALUES (?, ?, ?, ?, ?)',
                [$stokvelId, $ruleVersionId, $membershipId, now_utc(), now_utc()]
            );
        }
    }

    public function currentRulePack(int $stokvelId): array
    {
        return $this->db->fetchAll(
            'SELECT r.category, r.title, rv.version_number, rv.body, rv.effective_from
             FROM rules r
             INNER JOIN rule_versions rv ON rv.rule_id = r.id AND rv.stokvel_id = r.stokvel_id AND rv.status = ?
             WHERE r.stokvel_id = ?
             ORDER BY r.category, r.title',
            ['active', $stokvelId]
        );
    }
}
