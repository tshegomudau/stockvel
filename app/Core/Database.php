<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

final class Database
{
    private ?PDO $pdo = null;

    private int $transactionDepth = 0;

    public function __construct(
        private readonly array $config
    ) {
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset'] ?? 'utf8mb4'
        );

        try {
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'",
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $sql, array $params = []): string
    {
        $this->query($sql, $params);
        return $this->pdo()->lastInsertId();
    }

    public function exec(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function beginTransaction(): void
    {
        if ($this->transactionDepth === 0) {
            $this->pdo()->beginTransaction();
        } else {
            // Nested work uses savepoints so an inner failure does not abort the outer unit of work.
            $this->pdo()->exec('SAVEPOINT nest_' . $this->transactionDepth);
        }
        ++$this->transactionDepth;
    }

    public function commit(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }
        --$this->transactionDepth;
        if ($this->transactionDepth === 0) {
            $this->pdo()->commit();
        } else {
            $this->pdo()->exec('RELEASE SAVEPOINT nest_' . $this->transactionDepth);
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionDepth === 0) {
            return;
        }
        --$this->transactionDepth;
        if ($this->transactionDepth === 0) {
            if ($this->pdo()?->inTransaction()) {
                $this->pdo()->rollBack();
            }
        } else {
            $this->pdo()->exec('ROLLBACK TO SAVEPOINT nest_' . $this->transactionDepth);
        }
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
