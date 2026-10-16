<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Core\Database;
use App\Exceptions\FinanceException;
use App\Support\MoneyMath;

final class FeeStructureService
{
    public function __construct(
        private readonly Database $db
    ) {
    }

    public function createStructure(
        int $stokvelId,
        string $name,
        string $structureType,
        string $amount,
        string $frequency,
        int $gracePeriodDays = 0,
        string $penaltyRatePercent = '0.00',
        string $penaltyType = 'percent_of_outstanding',
        ?string $effectiveFrom = null,
        string $description = ''
    ): array {
        $amount = MoneyMath::normalize($amount);
        $effectiveFrom = $effectiveFrom ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $now = now_utc();

        return $this->db->transaction(function () use (
            $stokvelId,
            $name,
            $structureType,
            $amount,
            $frequency,
            $gracePeriodDays,
            $penaltyRatePercent,
            $penaltyType,
            $effectiveFrom,
            $description,
            $now
        ): array {
            $code = $this->nextStructureCode($stokvelId, $name);

            $structureId = (int) $this->db->insert(
                'INSERT INTO fee_structures
                    (stokvel_id, code, name, structure_type, status, description, is_active, created_at, updated_at)
                 VALUES
                    (:stokvel_id, :code, :name, :structure_type, :status, :description, 1, :created_at, :updated_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'code' => $code,
                    'name' => $name,
                    'structure_type' => $structureType,
                    'status' => 'active',
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $versionId = $this->createVersionInternal(
                $stokvelId,
                $structureId,
                1,
                $amount,
                $frequency,
                $gracePeriodDays,
                $penaltyRatePercent,
                $penaltyType,
                $effectiveFrom,
                null
            );

            $this->db->exec(
                'UPDATE fee_structures SET current_version_id = :version_id, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'version_id' => $versionId,
                    'updated_at' => $now,
                    'id' => $structureId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            return $this->getStructure($stokvelId, $structureId);
        });
    }

    public function createVersion(
        int $stokvelId,
        int $feeStructureId,
        string $amount,
        string $frequency,
        int $gracePeriodDays,
        string $penaltyRatePercent,
        string $penaltyType,
        string $effectiveFrom
    ): int {
        $structure = $this->getStructure($stokvelId, $feeStructureId);
        if ($structure === null) {
            throw new FinanceException('Fee structure not found.');
        }

        $latest = $this->getLatestVersion($stokvelId, $feeStructureId);
        $nextVersion = $latest !== null ? ((int) $latest['version_number']) + 1 : 1;

        if ($latest !== null && $this->versionHasCharges($stokvelId, (int) $latest['id'])) {
            $closeDate = (new \DateTimeImmutable($effectiveFrom))->modify('-1 day')->format('Y-m-d');
            $this->closeVersion($stokvelId, (int) $latest['id'], $closeDate);
        }

        return $this->db->transaction(function () use (
            $stokvelId,
            $feeStructureId,
            $nextVersion,
            $amount,
            $frequency,
            $gracePeriodDays,
            $penaltyRatePercent,
            $penaltyType,
            $effectiveFrom
        ): int {
            $versionId = $this->createVersionInternal(
                $stokvelId,
                $feeStructureId,
                $nextVersion,
                $amount,
                $frequency,
                $gracePeriodDays,
                $penaltyRatePercent,
                $penaltyType,
                $effectiveFrom,
                null
            );

            $this->db->exec(
                'UPDATE fee_structures SET current_version_id = :version_id, updated_at = :updated_at
                 WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'version_id' => $versionId,
                    'updated_at' => now_utc(),
                    'id' => $feeStructureId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            return $versionId;
        });
    }

    public function getStructure(int $stokvelId, int $feeStructureId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM fee_structures WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $feeStructureId, 'stokvel_id' => $stokvelId]
        );
    }

    public function listStructures(int $stokvelId, bool $activeOnly = true): array
    {
        $sql = 'SELECT fs.*, fsv.amount AS current_amount, fsv.frequency AS current_frequency
                FROM fee_structures fs
                LEFT JOIN fee_structure_versions fsv ON fsv.id = fs.current_version_id AND fsv.stokvel_id = fs.stokvel_id
                WHERE fs.stokvel_id = :stokvel_id';
        if ($activeOnly) {
            $sql .= ' AND fs.is_active = 1';
        }
        $sql .= ' ORDER BY fs.name ASC';

        return $this->db->fetchAll($sql, ['stokvel_id' => $stokvelId]);
    }

    public function getVersion(int $stokvelId, int $versionId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM fee_structure_versions WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $versionId, 'stokvel_id' => $stokvelId]
        );
    }

    public function getEffectiveVersion(int $stokvelId, int $feeStructureId, string $asOfDate): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM fee_structure_versions
             WHERE stokvel_id = :stokvel_id AND fee_structure_id = :fee_structure_id
               AND effective_from <= :as_of_from
               AND (effective_to IS NULL OR effective_to >= :as_of_to)
             ORDER BY version_number DESC LIMIT 1',
            [
                'stokvel_id' => $stokvelId,
                'fee_structure_id' => $feeStructureId,
                'as_of_from' => $asOfDate,
                'as_of_to' => $asOfDate,
            ]
        );
    }

    public function updateStructureMeta(int $stokvelId, int $feeStructureId, array $fields): void
    {
        $allowed = ['name', 'description', 'is_active'];
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $data[$key] = $fields[$key];
            }
        }
        if ($data === []) {
            return;
        }
        $data['updated_at'] = now_utc();
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "{$col} = :{$col}";
        }
        $params = array_merge($data, ['id' => $feeStructureId, 'stokvel_id' => $stokvelId]);
        $affected = $this->db->exec(
            'UPDATE fee_structures SET ' . implode(', ', $sets) . ' WHERE id = :id AND stokvel_id = :stokvel_id',
            $params
        );
        if ($affected === 0) {
            throw new FinanceException('Fee structure not found.');
        }
    }

    public function deactivate(int $stokvelId, int $feeStructureId): void
    {
        $this->updateStructureMeta($stokvelId, $feeStructureId, ['is_active' => 0]);
        $this->db->exec(
            'UPDATE fee_structures SET status = :status, updated_at = :updated_at
             WHERE id = :id AND stokvel_id = :stokvel_id',
            [
                'status' => 'inactive',
                'updated_at' => now_utc(),
                'id' => $feeStructureId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    private function nextStructureCode(int $stokvelId, string $name): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($name)) ?? 'fee');
        $base = trim($base, '-') ?: 'fee';
        $code = substr($base, 0, 48);
        $suffix = 0;
        while ($this->db->fetch(
            'SELECT id FROM fee_structures WHERE stokvel_id = :stokvel_id AND code = :code LIMIT 1',
            ['stokvel_id' => $stokvelId, 'code' => $code]
        ) !== null) {
            ++$suffix;
            $code = substr($base, 0, 44) . '-' . $suffix;
        }

        return $code;
    }

    private function getLatestVersion(int $stokvelId, int $feeStructureId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM fee_structure_versions
             WHERE stokvel_id = :stokvel_id AND fee_structure_id = :fee_structure_id
             ORDER BY version_number DESC LIMIT 1',
            ['stokvel_id' => $stokvelId, 'fee_structure_id' => $feeStructureId]
        );
    }

    private function versionHasCharges(int $stokvelId, int $versionId): bool
    {
        $row = $this->db->fetch(
            'SELECT COUNT(*) AS cnt FROM member_charges
             WHERE stokvel_id = :stokvel_id AND fee_structure_version_id = :version_id',
            ['stokvel_id' => $stokvelId, 'version_id' => $versionId]
        );

        return $row !== null && (int) $row['cnt'] > 0;
    }

    private function closeVersion(int $stokvelId, int $versionId, string $effectiveTo): void
    {
        $this->db->exec(
            'UPDATE fee_structure_versions SET effective_to = :effective_to
             WHERE id = :id AND stokvel_id = :stokvel_id AND effective_to IS NULL',
            [
                'effective_to' => $effectiveTo,
                'id' => $versionId,
                'stokvel_id' => $stokvelId,
            ]
        );
    }

    private function createVersionInternal(
        int $stokvelId,
        int $feeStructureId,
        int $versionNumber,
        string $amount,
        string $frequency,
        int $gracePeriodDays,
        string $penaltyRatePercent,
        string $penaltyType,
        string $effectiveFrom,
        ?string $effectiveTo
    ): int {
        $now = now_utc();

        return (int) $this->db->insert(
            'INSERT INTO fee_structure_versions
                (stokvel_id, fee_structure_id, version_number, amount, frequency, grace_days, grace_period_days,
                 penalty_rate_percent, penalty_type, effective_from, effective_to, created_at)
             VALUES
                (:stokvel_id, :fee_structure_id, :version_number, :amount, :frequency, :grace_days, :grace_period_days,
                 :penalty_rate_percent, :penalty_type, :effective_from, :effective_to, :created_at)',
            [
                'stokvel_id' => $stokvelId,
                'fee_structure_id' => $feeStructureId,
                'version_number' => $versionNumber,
                'amount' => MoneyMath::normalize($amount),
                'frequency' => $frequency,
                'grace_days' => $gracePeriodDays,
                'grace_period_days' => $gracePeriodDays,
                'penalty_rate_percent' => MoneyMath::normalize($penaltyRatePercent),
                'penalty_type' => $penaltyType,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'created_at' => $now,
            ]
        );
    }
}
