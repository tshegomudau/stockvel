<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Exceptions\HttpException;
use RuntimeException;

final class LocalObjectStorage implements ObjectStorageInterface
{
    public function __construct(private readonly string $root)
    {
        if (!is_dir($this->root) && !mkdir($this->root, 0755, true) && !is_dir($this->root)) {
            throw new RuntimeException('Unable to create storage root: ' . $this->root);
        }
    }

    public function putFile(string $localPath, string $key, string $mimeType): array
    {
        $dest = $this->absolute($key);
        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create upload directory.');
        }
        if (!copy($localPath, $dest)) {
            throw new RuntimeException('Failed to store uploaded file.');
        }

        return [
            'driver' => 'local',
            'path' => $key,
            'size' => (int) filesize($dest),
        ];
    }

    public function downloadToTemp(string $key): string
    {
        $src = $this->absolute($key);
        if (!is_file($src)) {
            throw new HttpException(404, 'File not found on disk.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'stovel_');
        if ($tmp === false || !copy($src, $tmp)) {
            throw new RuntimeException('Unable to stage file for download.');
        }

        return $tmp;
    }

    public function delete(string $key): void
    {
        $path = $this->absolute($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function exists(string $key): bool
    {
        return is_file($this->absolute($key));
    }

    public function driverName(): string
    {
        return 'local';
    }

    /** Absolute path for local-only callers (DocumentService download). */
    public function absolutePath(string $key): string
    {
        return $this->absolute($key);
    }

    private function absolute(string $key): string
    {
        $key = ltrim(str_replace(['..', '\\'], '', $key), '/');
        return rtrim($this->root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $key);
    }
}
