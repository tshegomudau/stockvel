<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Core\Database;
use App\Core\TenantContext;
use App\Exceptions\HttpException;
use App\Services\AuditService;
use App\Services\Storage\LocalObjectStorage;
use App\Services\Storage\StorageManager;

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
        private readonly AuditService $audit,
        private readonly StorageManager $storage,
    ) {
    }

    /** @param array<string, mixed> $file */
    public function upload(
        array $file,
        string $title,
        ?int $folderId,
        int $uploadedByUserId,
        ?string $category = null,
        ?int $documentCategoryId = null,
    ): array {
        $stokvelId = $this->tenant->requireStokvelId();
        $this->storage->assertUploadsEnabled();
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

        $random = bin2hex(random_bytes(16));
        $storedName = $random . '.' . $extension;
        $relativePath = $stokvelId . '/' . $storedName;

        $driver = $this->storage->driver();
        $stored = $driver->putFile($tmp, $relativePath, $mime);
        @unlink($tmp);

        $documentRef = 'DOC-' . strtoupper(substr($random, 0, 8));
        $size = (int) ($stored['size'] ?? ($file['size'] ?? 0));
        $storageDriver = (string) ($stored['driver'] ?? $driver->driverName());

        return $this->db->transaction(function () use (
            $stokvelId,
            $title,
            $folderId,
            $uploadedByUserId,
            $category,
            $documentCategoryId,
            $documentRef,
            $relativePath,
            $storedName,
            $mime,
            $size,
            $storageDriver,
        ) {
            $hasCategoryCol = $this->hasDocumentsCategoryColumn();
            if ($hasCategoryCol && $documentCategoryId !== null) {
                $documentId = (int) $this->db->insert(
                    'INSERT INTO documents (stokvel_id, folder_id, document_ref, title, category, document_category_id, status, content_type, uploaded_by_user_id, created_at, updated_at)
                     VALUES (:stokvel_id, :folder_id, :document_ref, :title, :category, :dcat, :status, :ctype, :uploaded_by_user_id, :created_at, :updated_at)',
                    [
                        'stokvel_id' => $stokvelId,
                        'folder_id' => $folderId,
                        'document_ref' => $documentRef,
                        'title' => $title,
                        'category' => $category,
                        'dcat' => $documentCategoryId,
                        'status' => 'active',
                        'ctype' => 'file',
                        'uploaded_by_user_id' => $uploadedByUserId,
                        'created_at' => now_utc(),
                        'updated_at' => now_utc(),
                    ]
                );
            } else {
                $documentId = (int) $this->db->insert(
                    'INSERT INTO documents (stokvel_id, folder_id, document_ref, title, category, status, content_type, uploaded_by_user_id, created_at, updated_at)
                     VALUES (:stokvel_id, :folder_id, :document_ref, :title, :category, :status, :ctype, :uploaded_by_user_id, :created_at, :updated_at)',
                    [
                        'stokvel_id' => $stokvelId,
                        'folder_id' => $folderId,
                        'document_ref' => $documentRef,
                        'title' => $title,
                        'category' => $category,
                        'status' => 'active',
                        'ctype' => 'file',
                        'uploaded_by_user_id' => $uploadedByUserId,
                        'created_at' => now_utc(),
                        'updated_at' => now_utc(),
                    ]
                );
            }

            $versionSql = $this->hasStorageDriverColumn()
                ? 'INSERT INTO document_versions (stokvel_id, document_id, version_number, file_name, storage_path, storage_driver, mime_type, file_size_bytes, uploaded_by_user_id, created_at)
                   VALUES (:stokvel_id, :document_id, 1, :file_name, :storage_path, :storage_driver, :mime_type, :file_size_bytes, :uploaded_by_user_id, :created_at)'
                : 'INSERT INTO document_versions (stokvel_id, document_id, version_number, file_name, storage_path, mime_type, file_size_bytes, uploaded_by_user_id, created_at)
                   VALUES (:stokvel_id, :document_id, 1, :file_name, :storage_path, :mime_type, :file_size_bytes, :uploaded_by_user_id, :created_at)';

            $versionParams = [
                'stokvel_id' => $stokvelId,
                'document_id' => $documentId,
                'file_name' => $storedName,
                'storage_path' => $relativePath,
                'mime_type' => $mime,
                'file_size_bytes' => $size,
                'uploaded_by_user_id' => $uploadedByUserId,
                'created_at' => now_utc(),
            ];
            if ($this->hasStorageDriverColumn()) {
                $versionParams['storage_driver'] = $storageDriver;
            }

            $versionId = (int) $this->db->insert($versionSql, $versionParams);

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
                'storage_driver' => $storageDriver,
            ]);

            return [
                'document_id' => $documentId,
                'version_id' => $versionId,
                'document_ref' => $documentRef,
            ];
        });
    }

    /**
     * Uploaded files: uploader or admin with documents.view.
     * Typed documents: shared with all active stokvel members.
     *
     * @param array<string, mixed> $document
     */
    public function canAccessDocument(array $document, int $userId, bool $asAdmin = false): bool
    {
        $type = (string) ($document['content_type'] ?? 'file');
        if ($type === 'typed') {
            return true;
        }

        if ((int) ($document['uploaded_by_user_id'] ?? 0) === $userId) {
            return true;
        }

        return $asAdmin && $this->tenant->can('documents.view');
    }

    /** @param array<string, mixed> $document */
    public function assertCanAccessDocument(array $document, int $userId, bool $asAdmin = false): void
    {
        if (!$this->canAccessDocument($document, $userId, $asAdmin)) {
            throw new HttpException(404, 'Document not found.');
        }
    }

    /**
     * SQL predicate for member portal lists (bind :doc_uid to current user id).
     */
    public function memberVisibilitySql(string $alias = 'd'): string
    {
        return "({$alias}.content_type = 'typed' OR {$alias}.uploaded_by_user_id = :doc_uid)";
    }

    /**
     * @return array{path:string,mime_type:string,file_name:string,document:array<string,mixed>,version:array<string,mixed>,temp:?string}
     */
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
            // Fall back to current version when version_number=1 is missing / only one version.
            $version = $this->db->fetch(
                'SELECT * FROM document_versions
                 WHERE document_id = :document_id AND stokvel_id = :stokvel_id
                 ORDER BY version_number DESC LIMIT 1',
                ['document_id' => $documentId, 'stokvel_id' => $stokvelId]
            );
        }
        if (!$version) {
            throw new HttpException(404, 'Document version not found.');
        }

        $relative = str_replace('\\', '/', (string) $version['storage_path']);
        if (str_contains($relative, '..') || str_starts_with($relative, '/')) {
            throw new HttpException(403, 'Invalid document path.');
        }

        $driverName = (string) ($version['storage_driver'] ?? 'local');
        $temp = null;

        if ($driverName === 'backblaze') {
            $temp = $this->storage->forDriver('backblaze')->downloadToTemp($relative);
            $full = $temp;
        } else {
            $local = new LocalObjectStorage(app()->basePath('storage/documents'));
            $full = $local->absolutePath($relative);
            if (!is_file($full)) {
                // Legacy absolute-resolution via realpath for older layouts.
                $base = realpath(app()->basePath('storage/documents'));
                if ($base === false) {
                    throw new HttpException(500, 'Document storage unavailable.');
                }
                $resolved = realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
                if ($resolved === false || !str_starts_with($resolved, $base . DIRECTORY_SEPARATOR)) {
                    throw new HttpException(404, 'Document file missing.');
                }
                $full = $resolved;
            }
        }

        if ($temp !== null) {
            register_shutdown_function(static function () use ($temp): void {
                if (is_string($temp) && is_file($temp)) {
                    @unlink($temp);
                }
            });
        }

        return [
            'path' => $full,
            'mime_type' => (string) $version['mime_type'],
            'file_name' => (string) ($version['file_name'] ?? basename($full)),
            'document' => $document,
            'version' => $version,
            'temp' => $temp,
        ];
    }

    /**
     * Soft-delete a document. Optionally remove stored file blobs for file docs.
     *
     * @return array<string, mixed>
     */
    public function softDelete(int $documentId, int $stokvelId, int $actorUserId, bool $asAdmin = false): array
    {
        $document = $this->db->fetch(
            "SELECT * FROM documents WHERE id = :id AND stokvel_id = :sid AND status <> 'deleted' LIMIT 1",
            ['id' => $documentId, 'sid' => $stokvelId]
        );
        if (!$document) {
            throw new HttpException(404, 'Document not found.');
        }

        $this->assertCanAccessDocument($document, $actorUserId, $asAdmin);

        // Members may only delete their own file uploads; admins may delete any.
        $type = (string) ($document['content_type'] ?? 'file');
        if (!$asAdmin) {
            if ($type === 'typed') {
                throw new HttpException(403, 'Typed documents can only be managed by stokvel admins.');
            }
            if ((int) ($document['uploaded_by_user_id'] ?? 0) !== $actorUserId) {
                throw new HttpException(404, 'Document not found.');
            }
        } elseif (!$this->tenant->can('documents.upload')) {
            throw new HttpException(403, 'You do not have permission to delete documents.');
        }

        $this->db->exec(
            "UPDATE documents SET status = 'deleted', updated_at = :ua WHERE id = :id AND stokvel_id = :sid",
            ['ua' => now_utc(), 'id' => $documentId, 'sid' => $stokvelId]
        );

        if ($type !== 'typed') {
            $this->purgeStoredVersions($documentId, $stokvelId);
        }

        $this->audit->log('document.deleted', 'document', $documentId, metadata: [
            'content_type' => $type,
            'as_admin' => $asAdmin,
        ]);

        return $document;
    }

    /**
     * Update metadata for a file document; optionally replace the binary with a new version.
     *
     * @param array<string, mixed>|null $file
     * @return array<string, mixed>
     */
    public function updateFileDocument(
        int $documentId,
        int $stokvelId,
        int $actorUserId,
        string $title,
        ?string $category,
        ?int $documentCategoryId,
        ?int $meetingId,
        ?array $file,
        bool $asAdmin = false,
    ): array {
        $document = $this->db->fetch(
            "SELECT * FROM documents WHERE id = :id AND stokvel_id = :sid AND status <> 'deleted' LIMIT 1",
            ['id' => $documentId, 'sid' => $stokvelId]
        );
        if (!$document) {
            throw new HttpException(404, 'Document not found.');
        }
        if ((string) ($document['content_type'] ?? '') === 'typed') {
            throw new HttpException(422, 'Use the typed document editor for this document.');
        }

        $this->assertCanAccessDocument($document, $actorUserId, $asAdmin);
        if (!$asAdmin && (int) ($document['uploaded_by_user_id'] ?? 0) !== $actorUserId) {
            throw new HttpException(404, 'Document not found.');
        }
        if ($asAdmin && !$this->tenant->can('documents.upload')) {
            throw new HttpException(403, 'You do not have permission to update documents.');
        }

        $title = trim($title);
        if ($title === '') {
            throw new HttpException(422, 'Title is required.');
        }

        $now = now_utc();
        $params = [
            'title' => $title,
            'ua' => $now,
            'id' => $documentId,
            'sid' => $stokvelId,
        ];
        $sets = 'title = :title, updated_at = :ua';

        if ($asAdmin) {
            $sets .= ', category = :cat, document_category_id = :dcat, meeting_id = :mid';
            $params['cat'] = $category ?: (string) ($document['category'] ?? 'general');
            $params['dcat'] = $documentCategoryId;
            $params['mid'] = $meetingId;
        }

        $this->db->exec(
            "UPDATE documents SET {$sets} WHERE id = :id AND stokvel_id = :sid AND content_type <> 'typed'",
            $params
        );

        if ($file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $this->addFileVersion($documentId, $stokvelId, $actorUserId, $file);
        }

        $this->audit->log('document.updated', 'document', $documentId, metadata: [
            'content_type' => 'file',
            'replaced_file' => $file !== null && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK,
        ]);

        $fresh = $this->db->fetch(
            'SELECT * FROM documents WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $documentId, 'sid' => $stokvelId]
        );

        return $fresh ?: $document;
    }

    /** @param array<string, mixed> $file */
    private function addFileVersion(int $documentId, int $stokvelId, int $uploadedByUserId, array $file): void
    {
        $this->storage->assertUploadsEnabled();
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

        $random = bin2hex(random_bytes(16));
        $storedName = $random . '.' . $extension;
        $relativePath = $stokvelId . '/' . $storedName;
        $driver = $this->storage->driver();
        $stored = $driver->putFile($tmp, $relativePath, $mime);

        $nextVersion = (int) ($this->db->fetch(
            'SELECT COALESCE(MAX(version_number), 0) AS v FROM document_versions
             WHERE document_id = :did AND stokvel_id = :sid',
            ['did' => $documentId, 'sid' => $stokvelId]
        )['v'] ?? 0) + 1;

        $versionSql = $this->hasStorageDriverColumn()
            ? 'INSERT INTO document_versions (stokvel_id, document_id, version_number, file_name, storage_path, storage_driver, mime_type, file_size_bytes, uploaded_by_user_id, created_at)
               VALUES (:stokvel_id, :document_id, :version_number, :file_name, :storage_path, :storage_driver, :mime_type, :file_size_bytes, :uploaded_by_user_id, :created_at)'
            : 'INSERT INTO document_versions (stokvel_id, document_id, version_number, file_name, storage_path, mime_type, file_size_bytes, uploaded_by_user_id, created_at)
               VALUES (:stokvel_id, :document_id, :version_number, :file_name, :storage_path, :mime_type, :file_size_bytes, :uploaded_by_user_id, :created_at)';

        $versionParams = [
            'stokvel_id' => $stokvelId,
            'document_id' => $documentId,
            'version_number' => $nextVersion,
            'file_name' => $storedName,
            'storage_path' => $relativePath,
            'mime_type' => $mime,
            'file_size_bytes' => (int) ($stored['size'] ?? ($file['size'] ?? 0)),
            'uploaded_by_user_id' => $uploadedByUserId,
            'created_at' => now_utc(),
        ];
        if ($this->hasStorageDriverColumn()) {
            $versionParams['storage_driver'] = (string) ($stored['driver'] ?? $driver->driverName());
        }

        $versionId = (int) $this->db->insert($versionSql, $versionParams);
        $this->db->exec(
            'UPDATE documents SET current_version_id = :vid, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['vid' => $versionId, 'ua' => now_utc(), 'id' => $documentId, 'sid' => $stokvelId]
        );
    }

    private function purgeStoredVersions(int $documentId, int $stokvelId): void
    {
        $versions = $this->hasStorageDriverColumn()
            ? $this->db->fetchAll(
                'SELECT storage_path, storage_driver FROM document_versions
                 WHERE document_id = :did AND stokvel_id = :sid',
                ['did' => $documentId, 'sid' => $stokvelId]
            )
            : $this->db->fetchAll(
                'SELECT storage_path FROM document_versions
                 WHERE document_id = :did AND stokvel_id = :sid',
                ['did' => $documentId, 'sid' => $stokvelId]
            );

        foreach ($versions as $version) {
            $path = (string) ($version['storage_path'] ?? '');
            if ($path === '' || str_contains($path, '..')) {
                continue;
            }
            $driverName = (string) ($version['storage_driver'] ?? 'local');
            try {
                $this->storage->forDriver($driverName)->delete($path);
            } catch (\Throwable) {
                // Best-effort cleanup; soft-delete already hides the document.
            }
        }
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

    private function hasStorageDriverColumn(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $this->db->fetch('SELECT storage_driver FROM document_versions LIMIT 1');
            $has = true;
        } catch (\Throwable) {
            $has = false;
        }

        return $has;
    }

    private function hasDocumentsCategoryColumn(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $this->db->fetch('SELECT document_category_id FROM documents LIMIT 1');
            $has = true;
        } catch (\Throwable) {
            $has = false;
        }

        return $has;
    }
}
