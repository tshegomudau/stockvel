<?php

declare(strict_types=1);

namespace App\Services\Storage;

interface ObjectStorageInterface
{
    /**
     * Store a local file at the given relative key.
     *
     * @return array{driver:string,path:string,size:int}
     */
    public function putFile(string $localPath, string $key, string $mimeType): array;

    /** Download object to a local temp path; caller should unlink when done. */
    public function downloadToTemp(string $key): string;

    public function delete(string $key): void;

    public function exists(string $key): bool;

    public function driverName(): string;
}
