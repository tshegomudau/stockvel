<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Services\Storage\LocalObjectStorage;
use App\Services\Storage\StorageManager;

final class MediaController extends Controller
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function upload(Request $request): Response
    {
        $this->requirePermission('documents.upload');
        $sid = $this->requireStokvel();

        $editor = app()->container()
            ->get(\App\Services\Stokvel\StokvelEditorSettingsService::class)
            ->forStokvel($sid);
        if (empty($editor['wysiwyg_full_enabled']) || empty($editor['wysiwyg_images_enabled'])) {
            return Response::json(['error' => 'Image inserts are disabled for this stokvel.'], 403);
        }

        /** @var StorageManager $storage */
        $storage = app()->container()->get(StorageManager::class);
        try {
            $storage->assertUploadsEnabled($sid);
        } catch (HttpException $e) {
            return Response::json(['error' => $e->getMessage()], $e->getStatus());
        }

        $file = $request->file('file') ?? $_FILES['file'] ?? null;
        if (!is_array($file)) {
            return Response::json(['error' => 'No file uploaded.'], 422);
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return Response::json(['error' => 'Upload failed.'], 422);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            return Response::json(['error' => 'Only JPEG, PNG, WebP, or GIF images are allowed.'], 422);
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
        $key = $sid . '/media/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $driver = $storage->driver();
        $stored = $driver->putFile($tmp, $key, $mime);

        $id = (int) $this->db()->insert(
            'INSERT INTO media_files (stokvel_id, storage_driver, storage_path, file_name, mime_type, file_size_bytes, uploaded_by_user_id, created_at)
             VALUES (:sid, :driver, :path, :name, :mime, :size, :by, :ca)',
            [
                'sid' => $sid,
                'driver' => (string) ($stored['driver'] ?? $driver->driverName()),
                'path' => (string) ($stored['path'] ?? $key),
                'name' => (string) ($file['name'] ?? basename($key)),
                'mime' => $mime,
                'size' => (int) ($stored['size'] ?? 0),
                'by' => $this->userId(),
                'ca' => now_utc(),
            ]
        );

        return Response::json([
            'id' => $id,
            'url' => url('/app/media/' . $id),
            'mime_type' => $mime,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch(
            'SELECT * FROM media_files WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$row) {
            return Response::html('Not found', 404);
        }

        /** @var StorageManager $storage */
        $storage = app()->container()->get(StorageManager::class);
        $driverName = (string) ($row['storage_driver'] ?? 'local');
        $pathKey = (string) $row['storage_path'];
        $temp = null;

        try {
            if ($driverName === 'backblaze') {
                $temp = $storage->forDriver('backblaze')->downloadToTemp($pathKey);
                $path = $temp;
            } else {
                $local = new LocalObjectStorage(app()->basePath('storage/documents'));
                $path = $local->absolutePath($pathKey);
                if (!is_file($path)) {
                    return Response::html('Not found', 404);
                }
            }
        } catch (\Throwable) {
            return Response::html('Not found', 404);
        }

        $content = file_get_contents($path) ?: '';
        if ($temp !== null) {
            @unlink($temp);
        }

        return new Response($content, 200, [
            'Content-Type' => (string) $row['mime_type'],
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
