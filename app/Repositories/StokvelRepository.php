<?php

declare(strict_types=1);

namespace App\Repositories;

final class StokvelRepository extends BaseRepository
{
    protected string $table = 'stokvels';
    protected bool $tenantScoped = false;

    public function findById(int $id): ?array
    {
        $sql = 'SELECT * FROM stokvels WHERE id = :id LIMIT 1';
        return $this->db->fetch($sql, ['id' => $id]);
    }

    public function findBySlug(string $slug): ?array
    {
        $sql = 'SELECT * FROM stokvels WHERE slug = :slug LIMIT 1';
        return $this->db->fetch($sql, ['slug' => strtolower(trim($slug))]);
    }

    public function findByUuid(string $uuid): ?array
    {
        $sql = 'SELECT * FROM stokvels WHERE uuid = :uuid LIMIT 1';
        return $this->db->fetch($sql, ['uuid' => $uuid]);
    }

    public function create(array $data): int
    {
        $now = now_utc();
        if (!isset($data['uuid'])) {
            $data['uuid'] = uuid();
        }
        if (isset($data['slug'])) {
            $data['slug'] = strtolower(trim((string) $data['slug']));
        }

        try {
            return (int) $this->db->insert(
                'INSERT INTO stokvels (uuid, name, slug, stokvel_type, status, timezone, currency_code, created_by_user_id, created_at, updated_at)
                 VALUES (:uuid, :name, :slug, :stokvel_type, :status, :timezone, :currency_code, :created_by_user_id, :created_at, :updated_at)',
                [
                    'uuid' => $data['uuid'],
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                    'stokvel_type' => $data['stokvel_type'] ?? 'general_savings',
                    'status' => $data['status'] ?? 'draft',
                    'timezone' => $data['timezone'] ?? env('APP_TIMEZONE', 'Africa/Johannesburg'),
                    'currency_code' => $data['currency_code'] ?? env('APP_CURRENCY', 'ZAR'),
                    'created_by_user_id' => $data['created_by_user_id'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        } catch (\Throwable) {
            if (!isset($data['status'])) {
                $data['status'] = 'draft';
            }
            return $this->insert($data);
        }
    }

    public function getOnboarding(int $stokvelId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM stokvel_onboarding WHERE stokvel_id = :stokvel_id LIMIT 1',
            ['stokvel_id' => $stokvelId]
        );
    }

    /** @param array<string, mixed> $progress @param list<int>|null $stepsCompleted */
    public function saveOnboarding(int $stokvelId, int $step, array $progress, ?array $stepsCompleted = null): void
    {
        $now = now_utc();
        $existing = $this->getOnboarding($stokvelId);
        $json = json_encode($progress, JSON_THROW_ON_ERROR);
        $stepsJson = $stepsCompleted !== null
            ? json_encode(array_values(array_unique(array_map('intval', $stepsCompleted))), JSON_THROW_ON_ERROR)
            : null;
        if ($existing) {
            if ($stepsJson !== null) {
                $this->db->exec(
                    'UPDATE stokvel_onboarding
                     SET current_step = :step, wizard_data = :wizard_data, steps_completed = :steps_completed, updated_at = :updated_at
                     WHERE stokvel_id = :stokvel_id',
                    [
                        'step' => $step,
                        'wizard_data' => $json,
                        'steps_completed' => $stepsJson,
                        'updated_at' => $now,
                        'stokvel_id' => $stokvelId,
                    ]
                );
                return;
            }
            $this->db->exec(
                'UPDATE stokvel_onboarding SET current_step = :step, wizard_data = :wizard_data, updated_at = :updated_at
                 WHERE stokvel_id = :stokvel_id',
                [
                    'step' => $step,
                    'wizard_data' => $json,
                    'updated_at' => $now,
                    'stokvel_id' => $stokvelId,
                ]
            );
            return;
        }

        $this->db->insert(
            'INSERT INTO stokvel_onboarding (stokvel_id, current_step, steps_completed, wizard_data, created_at, updated_at)
             VALUES (:stokvel_id, :step, :steps_completed, :wizard_data, :created_at, :updated_at)',
            [
                'stokvel_id' => $stokvelId,
                'step' => $step,
                'steps_completed' => $stepsJson ?? json_encode([], JSON_THROW_ON_ERROR),
                'wizard_data' => $json,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function activate(int $id): void
    {
        $now = now_utc();
        // Use direct exec so already-active rows (0 MySQL "changed" rows) do not 404.
        $this->db->exec(
            "UPDATE stokvels
             SET status = 'active',
                 activated_at = COALESCE(activated_at, :aa),
                 updated_at = :ua
             WHERE id = :id",
            ['aa' => $now, 'ua' => $now, 'id' => $id]
        );
    }

    public function suspend(int $id, ?string $reason = null): void
    {
        $payload = [
            'status' => 'suspended',
            'suspended_at' => now_utc(),
        ];
        if ($reason !== null) {
            $payload['suspension_reason'] = $reason;
        }
        $this->update($id, $payload);
    }
}
