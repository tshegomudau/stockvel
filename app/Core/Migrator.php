<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;
use Throwable;

final class Migrator
{
    private const MIGRATIONS_TABLE = 'migrations';

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationsPath
    ) {
    }

    /**
     * @return list<string> Names of migrations executed in this run.
     */
    public function migrate(): array
    {
        $this->ensureMigrationsTable();

        $executed = [];
        $batch = $this->nextBatchNumber();

        foreach ($this->migrationFiles() as $path) {
            $name = basename($path);
            if ($this->hasRun($name)) {
                continue;
            }

            $migration = $this->loadMigration($path);
            $this->runStatements($migration['up']);
            $this->recordMigration($name, $batch);
            $executed[] = $name;
        }

        return $executed;
    }

    /**
     * @return list<string> Names of migrations rolled back in this run.
     */
    public function rollback(?int $steps = null): array
    {
        $this->ensureMigrationsTable();

        $rolledBack = [];
        $batches = $this->pendingRollbackBatches($steps);

        foreach ($batches as $batch) {
            $names = $this->migrationsInBatch($batch);
            foreach (array_reverse($names) as $name) {
                $path = $this->migrationsPath . DIRECTORY_SEPARATOR . $name;
                if (!is_file($path)) {
                    throw new RuntimeException("Migration file missing for rollback: {$name}");
                }

                $migration = $this->loadMigration($path);
                $this->runStatements($migration['down']);
                $this->removeMigrationRecord($name);
                $rolledBack[] = $name;
            }
        }

        return $rolledBack;
    }

    /**
     * @return array{ran: list<string>, pending: list<string>}
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();

        $ran = $this->ranMigrationNames();
        $all = array_map(static fn (string $path): string => basename($path), $this->migrationFiles());
        $pending = array_values(array_diff($all, $ran));

        return ['ran' => $ran, 'pending' => $pending];
    }

    private function ensureMigrationsTable(): void
    {
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS migrations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    migration VARCHAR(255) NOT NULL,
    batch INT UNSIGNED NOT NULL,
    executed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_migrations_migration (migration),
    KEY idx_migrations_batch (batch)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
        $this->pdo->exec($sql);
    }

    /**
     * @return list<string> Absolute paths, sorted by filename.
     */
    private function migrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }

        $files = glob($this->migrationsPath . DIRECTORY_SEPARATOR . '*.php');
        if ($files === false) {
            return [];
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @return array{up: list<string>, down: list<string>}
     */
    private function loadMigration(string $path): array
    {
        $migration = require $path;
        if (!is_array($migration)) {
            throw new RuntimeException('Migration must return an array: ' . basename($path));
        }

        foreach (['up', 'down'] as $key) {
            if (!isset($migration[$key]) || !is_array($migration[$key])) {
                throw new RuntimeException("Migration {$key} must be an array of SQL strings: " . basename($path));
            }
            foreach ($migration[$key] as $index => $sql) {
                if (!is_string($sql) || trim($sql) === '') {
                    throw new RuntimeException("Invalid SQL at {$key}[{$index}] in " . basename($path));
                }
            }
        }

        /** @var array{up: list<string>, down: list<string>} $migration */
        return $migration;
    }

    /**
     * @param list<string> $statements
     */
    private function runStatements(array $statements): void
    {
        foreach ($statements as $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (Throwable $e) {
                $message = $e->getMessage();
                // Idempotent ALTER TABLE ADD COLUMN / ADD INDEX on partially applied upgrades.
                if (
                    str_contains($message, 'Duplicate column name')
                    || str_contains($message, 'Duplicate key name')
                    || str_contains($message, 'already exists')
                ) {
                    continue;
                }
                throw new RuntimeException('Migration SQL failed: ' . $message, 0, $e);
            }
        }
    }

    private function hasRun(string $name): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM ' . self::MIGRATIONS_TABLE . ' WHERE migration = :migration LIMIT 1'
        );
        $stmt->execute(['migration' => $name]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private function ranMigrationNames(): array
    {
        $stmt = $this->pdo->query(
            'SELECT migration FROM ' . self::MIGRATIONS_TABLE . ' ORDER BY id ASC'
        );
        if ($stmt === false) {
            return [];
        }

        /** @var list<string> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $rows;
    }

    private function nextBatchNumber(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM ' . self::MIGRATIONS_TABLE);
        if ($stmt === false) {
            return 1;
        }

        return (int) $stmt->fetchColumn();
    }

    private function recordMigration(string $name, int $batch): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . self::MIGRATIONS_TABLE . ' (migration, batch, executed_at) VALUES (:migration, :batch, :executed_at)'
        );
        $stmt->execute([
            'migration' => $name,
            'batch' => $batch,
            'executed_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function removeMigrationRecord(string $name): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM ' . self::MIGRATIONS_TABLE . ' WHERE migration = :migration'
        );
        $stmt->execute(['migration' => $name]);
    }

    /**
     * @return list<int>
     */
    private function pendingRollbackBatches(?int $steps): array
    {
        $sql = 'SELECT DISTINCT batch FROM ' . self::MIGRATIONS_TABLE . ' ORDER BY batch DESC';
        if ($steps !== null && $steps > 0) {
            $sql .= ' LIMIT ' . (int) $steps;
        }

        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            return [];
        }

        /** @var list<int|string> $batches */
        $batches = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_map(static fn ($b): int => (int) $b, $batches);
    }

    /**
     * @return list<string>
     */
    private function migrationsInBatch(int $batch): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT migration FROM ' . self::MIGRATIONS_TABLE . ' WHERE batch = :batch ORDER BY id ASC'
        );
        $stmt->execute(['batch' => $batch]);

        /** @var list<string> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $rows;
    }
}
