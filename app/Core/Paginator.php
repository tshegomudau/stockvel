<?php

declare(strict_types=1);

namespace App\Core;

final class Paginator
{
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly string $path = '',
        public readonly array $query = []
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function hasPages(): bool
    {
        return $this->lastPage() > 1;
    }

    public function url(int $page): string
    {
        $query = array_merge($this->query, ['page' => $page]);
        return $this->path . '?' . http_build_query($query);
    }

    public static function fromQuery(Database $db, string $countSql, string $dataSql, array $params, int $page, int $perPage, string $path = '', array $query = []): self
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = (int) ($db->fetch($countSql, $params)['c'] ?? 0);
        $offset = ($page - 1) * $perPage;
        $items = $db->fetchAll($dataSql . " LIMIT {$perPage} OFFSET {$offset}", $params);
        return new self($items, $total, $page, $perPage, $path, $query);
    }
}
