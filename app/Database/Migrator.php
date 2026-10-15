<?php

declare(strict_types=1);

namespace App\Database;

use App\Core\Database;
use RuntimeException;

final class Migrator
{
    public function __construct(
        private readonly Database $db,
        private readonly string $migrationsPath,
        private readonly string $migrationsTable = 'migrations'
    ) {
    }

    public function migrate(): int
    {
        $this->ensureMigrationsTable();
        $applied = $this->applied();
        $files = $this->migrationFiles();
        $count = 0;

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $applied, true)) {
                continue;
            }
            $migration = require $file;
            if (!is_object($migration) || !method_exists($migration, 'up')) {
                throw new RuntimeException("Invalid migration: {$name}");
            }
            $this->db->transaction(function () use ($migration, $name, &$count) {
                $migration->up($this->db);
                $this->db->insert(
                    "INSERT INTO {$this->migrationsTable} (migration, batch, applied_at) VALUES (:m, :b, :t)",
                    ['m' => $name, 'b' => $this->nextBatch(), 't' => now_utc()]
                );
                $count++;
            });
        }

        return $count;
    }

    public function rollback(int $steps = 1): int
    {
        $this->ensureMigrationsTable();
        $rows = $this->db->fetchAll(
            "SELECT migration FROM {$this->migrationsTable} ORDER BY id DESC LIMIT " . (int) $steps
        );
        $count = 0;
        foreach ($rows as $row) {
            $file = $this->migrationsPath . '/' . $row['migration'] . '.php';
            if (!is_file($file)) {
                continue;
            }
            $migration = require $file;
            $this->db->transaction(function () use ($migration, $row, &$count) {
                if (method_exists($migration, 'down')) {
                    $migration->down($this->db);
                }
                $this->db->exec(
                    "DELETE FROM {$this->migrationsTable} WHERE migration = :m",
                    ['m' => $row['migration']]
                );
                $count++;
            });
        }
        return $count;
    }

    public function fresh(): void
    {
        $pdo = $this->db->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $tables = $this->db->fetchAll(
            'SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        );
        foreach ($tables as $t) {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $t['name']) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $this->migrate();
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->pdo()->exec(
            "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT UNSIGNED NOT NULL,
                applied_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @return list<string> */
    private function applied(): array
    {
        try {
            return array_column(
                $this->db->fetchAll("SELECT migration FROM {$this->migrationsTable} ORDER BY id"),
                'migration'
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return list<string> */
    private function migrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.php') ?: [];
        sort($files);
        return $files;
    }

    private function nextBatch(): int
    {
        $row = $this->db->fetch("SELECT MAX(batch) AS b FROM {$this->migrationsTable}");
        return (int) (($row['b'] ?? 0) + 1);
    }
}
