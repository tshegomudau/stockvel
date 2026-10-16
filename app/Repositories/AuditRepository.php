<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class AuditRepository
{
    public function __construct(
        private readonly Database $db
    ) {
    }

    public function append(array $data): int
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = now_utc();
        }

        $entityType = (string) ($data['entity_type'] ?? 'system');
        $payload = [
            'stokvel_id' => $data['stokvel_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'action' => (string) $data['action'],
            'entity_type' => $entityType,
            'entity_id' => $data['entity_id'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'created_at' => $data['created_at'],
        ];

        if (isset($data['old_values_json']) || isset($data['old_values'])) {
            $old = $data['old_values_json'] ?? $data['old_values'];
            $payload['old_values_json'] = is_string($old) ? $old : json_encode($old, JSON_THROW_ON_ERROR);
        }
        if (isset($data['new_values_json']) || isset($data['new_values'])) {
            $new = $data['new_values_json'] ?? $data['new_values'];
            $payload['new_values_json'] = is_string($new) ? $new : json_encode($new, JSON_THROW_ON_ERROR);
        }
        if (isset($data['metadata_json']) || isset($data['metadata'])) {
            $meta = $data['metadata_json'] ?? $data['metadata'];
            $payload['metadata_json'] = is_string($meta) ? $meta : json_encode($meta, JSON_THROW_ON_ERROR);
        }

        if ($payload['user_id'] !== null) {
            $exists = $this->db->fetch('SELECT id FROM users WHERE id = :id LIMIT 1', ['id' => $payload['user_id']]);
            if ($exists === null) {
                $payload['user_id'] = null;
            }
        }

        $columns = array_keys($payload);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO audit_logs (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        try {
            return (int) $this->db->insert($sql, $payload);
        } catch (\Throwable) {
            $legacy = [
                'stokvel_id' => $payload['stokvel_id'],
                'user_id' => $payload['user_id'],
                'action' => $payload['action'],
                'entity_type' => $payload['entity_type'],
                'entity_id' => $payload['entity_id'],
                'metadata_json' => $payload['metadata_json'] ?? null,
                'ip_address' => $payload['ip_address'],
                'created_at' => $payload['created_at'],
            ];
            $columns = array_keys($legacy);
            $placeholders = array_map(static fn ($c) => ':' . $c, $columns);
            $sql = sprintf(
                'INSERT INTO audit_logs (%s) VALUES (%s)',
                implode(', ', $columns),
                implode(', ', $placeholders)
            );
            return (int) $this->db->insert($sql, $legacy);
        }
    }
}
