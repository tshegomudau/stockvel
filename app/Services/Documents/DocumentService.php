<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Core\Database;
use App\Core\TenantContext;
use App\Exceptions\HttpException;
use App\Services\AuditService;

final class DocumentService
{
    /** @var string[] */
    private const BLOCKED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh', 'bat', 'cmd', 'com', 'js', 'html', 'htm',
    ];

    /** @var string[] */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'text/plain',
        'text/csv',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly TenantContext $tenant,
        private readonly AuditService $audit
    ) {
    }

    /** @param array<string, mixed> $file */
    public function upload(array $file, string $title, ?int $folderId, int $uploadedByUserId, ?string $category = null): array
    {
        $stokvelId = $this->tenant->requireStokvelId();
        $this->validateUpload($file);

        $tmp = (string) ($file['tmp_name'] ?? '');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: 'application/octet-stream';
        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            throw new HttpException(422, 'File type is not allowed.');
        }

        $original = (string) ($file['name'] ?? 'document');
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new HttpException(422, 'File extension is not allowed.');
        }

        $storageDir = app()->basePath('storage/documents/' . $stokvelId);
        if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            throw new HttpException(500, 'Unable to prepare document storage.');
        }

        $random = bin2hex(random_bytes(16));
        $storedName = $random . '.' . $extension;
        $relativePath = $stokvelId . '/' . $storedName;
        $absolutePath = $storageDir . '/' . $storedName;

        if (!move_uploaded_file($tmp, $absolutePath)) {
            throw new HttpException(500, 'Failed to store uploaded file.');
        }

        $documentRef = 'DOC-' . strtoupper(substr($random, 0, 8));
        $size = (int) ($file['size'] ?? filesize($absolutePath));

        return $this->db->transaction(function () use (
            $stokvelId,
            $title,
            $folderId,
            $uploadedByUserId,
            $category,
            $documentRef,
            $relativePath,
            $storedName,
            $mime,
            $size
        ) {
            $documentId = (int) $this->db->insert(
                'INSERT INTO documents (stokvel_id, folder_id, document_ref, title, category, status, uploaded_by_user_id, created_at, updated_at)
                 VALUES (:stokvel_id, :folder_id, :document_ref, :title, :category, :status, :uploaded_by_user_id, :created_at, :updated_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'folder_id' => $folderId,
                    'document_ref' => $documentRef,
                    'title' => $title,
                    'category' => $category,
                    'status' => 'active',
                    'uploaded_by_user_id' => $uploadedByUserId,
                    'created_at' => now_utc(),
                    'updated_at' => now_utc(),
                ]
            );

            $versionId = (int) $this->db->insert(
                'INSERT INTO document_versions (stokvel_id, document_id, version_number, file_name, storage_path, mime_type, file_size_bytes, uploaded_by_user_id, created_at)
                 VALUES (:stokvel_id, :document_id, 1, :file_name, :storage_path, :mime_type, :file_size_bytes, :uploaded_by_user_id, :created_at)',
                [
                    'stokvel_id' => $stokvelId,
                    'document_id' => $documentId,
                    'file_name' => $storedName,
                    'storage_path' => $relativePath,
                    'mime_type' => $mime,
                    'file_size_bytes' => $size,
                    'uploaded_by_user_id' => $uploadedByUserId,
                    'created_at' => now_utc(),
                ]
            );

            $this->db->exec(
                'UPDATE documents SET current_version_id = :version_id, updated_at = :updated_at WHERE id = :id AND stokvel_id = :stokvel_id',
                [
                    'version_id' => $versionId,
                    'updated_at' => now_utc(),
                    'id' => $documentId,
                    'stokvel_id' => $stokvelId,
                ]
            );

            $this->audit->log('document.uploaded', 'document', $documentId, metadata: [
                'document_ref' => $documentRef,
                'mime_type' => $mime,
            ]);

            return [
                'document_id' => $documentId,
                'version_id' => $versionId,
                'document_ref' => $documentRef,
            ];
        });
    }

    public function resolveDownloadPath(int $documentId, int $versionNumber, int $stokvelId): array
    {
        $document = $this->db->fetch(
            'SELECT * FROM documents WHERE id = :id AND stokvel_id = :stokvel_id LIMIT 1',
            ['id' => $documentId, 'stokvel_id' => $stokvelId]
        );
        if (!$document) {
            throw new HttpException(404, 'Document not found.');
        }

        $version = $this->db->fetch(
            'SELECT * FROM document_versions
             WHERE document_id = :document_id AND stokvel_id = :stokvel_id AND version_number = :version_number
             LIMIT 1',
            [
                'document_id' => $documentId,
                'stokvel_id' => $stokvelId,
                'version_number' => $versionNumber,
            ]
        );
        if (!$version) {
            throw new HttpException(404, 'Document version not found.');
        }

        $base = realpath(app()->basePath('storage/documents'));
        if ($base === false) {
            throw new HttpException(500, 'Document storage unavailable.');
        }

        $relative = str_replace('\\', '/', (string) $version['storage_path']);
        if (str_contains($relative, '..') || str_starts_with($relative, '/')) {
            throw new HttpException(403, 'Invalid document path.');
        }

        $full = realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
            throw new HttpException(404, 'Document file missing.');
        }

        return [
            'path' => $full,
            'mime_type' => (string) $version['mime_type'],
            'file_name' => (string) ($version['file_name'] ?? basename($full)),
            'document' => $document,
            'version' => $version,
        ];
    }

    /** @param array<string, mixed> $file */
    private function validateUpload(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new HttpException(422, 'Upload failed.');
        }

        $maxMb = (int) env('UPLOAD_MAX_MB', 10);
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > ($maxMb * 1024 * 1024)) {
            throw new HttpException(422, 'File exceeds maximum allowed size.');
        }
    }
}
