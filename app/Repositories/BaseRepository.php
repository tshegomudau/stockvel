<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\TenantContext;
use App\Exceptions\HttpException;

abstract class BaseRepository
{
    protected string $table;
    protected bool $tenantScoped = true;

    public function __construct(
        protected readonly Database $db,
        protected readonly TenantContext $tenant
    ) {
    }

    protected function stokvelId(): int
    {
        return $this->tenant->requireStokvelId();
    }

    protected function applyTenant(string $alias = ''): string
    {
        if (!$this->tenantScoped) {
            return '1=1';
        }
        $col = $alias !== '' ? "{$alias}.stokvel_id" : 'stokvel_id';
        return "{$col} = :_stokvel_id";
    }

    protected function tenantParams(array $params = []): array
    {
        if ($this->tenantScoped) {
            $params['_stokvel_id'] = $this->stokvelId();
        }
        return $params;
    }

    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND " . $this->applyTenant() . ' LIMIT 1';
        return $this->db->fetch($sql, $this->tenantParams(['id' => $id]));
    }

    public function findOrFail(int $id): array
    {
        $row = $this->find($id);
        if (!$row) {
            throw new HttpException(404, 'Record not found');
        }
        return $row;
    }

    public function all(string $orderBy = 'id DESC', int $limit = 100): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE " . $this->applyTenant() . " ORDER BY {$orderBy} LIMIT {$limit}";
        return $this->db->fetchAll($sql, $this->tenantParams());
    }

    public function insert(array $data): int
    {
        if ($this->tenantScoped && !isset($data['stokvel_id'])) {
            $data['stokvel_id'] = $this->stokvelId();
        }
        if (!isset($data['created_at'])) {
            $data['created_at'] = now_utc();
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = now_utc();
        }

        $columns = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        return (int) $this->db->insert($sql, $data);
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = now_utc();
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "{$col} = :{$col}";
        }
        $params = $this->tenantParams(array_merge($data, ['id' => $id]));
        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets) .
            ' WHERE id = :id AND ' . $this->applyTenant();
        $affected = $this->db->exec($sql, $params);
        if ($affected === 0) {
            throw new HttpException(404, 'Record not found or access denied');
        }
    }

    public function softDelete(int $id): void
    {
        $this->update($id, ['deleted_at' => now_utc()]);
    }
}
