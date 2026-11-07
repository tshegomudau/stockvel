<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Core\TenantContext;
use App\Exceptions\HttpException;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Stokvel\StokvelEditorSettingsService;

final class StorageManager
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly string $localRoot,
    ) {
    }

    public function uploadsEnabled(?int $stokvelId = null): bool
    {
        $stokvelId = $stokvelId ?? $this->currentStokvelId();
        if ($stokvelId !== null && $stokvelId > 0) {
            try {
                return app()->container()
                    ->get(StokvelEditorSettingsService::class)
                    ->enabled($stokvelId, 'file_uploads_enabled');
            } catch (\Throwable) {
                // Fall through to platform default.
            }
        }

        return $this->settings->enabled('file_uploads_enabled');
    }

    public function assertUploadsEnabled(?int $stokvelId = null): void
    {
        if (!$this->uploadsEnabled($stokvelId)) {
            throw new HttpException(
                403,
                'File uploads are disabled for this stokvel. Ask a platform admin to enable them for this stokvel.'
            );
        }
    }

    public function driver(?int $stokvelId = null): ObjectStorageInterface
    {
        $this->assertUploadsEnabled($stokvelId);
        $name = (string) $this->settings->get('storage_driver', 'local');

        if ($name === 'backblaze') {
            return new BackblazeB2Storage(
                (string) $this->settings->get('b2_key_id', ''),
                (string) $this->settings->get('b2_application_key', ''),
                (string) $this->settings->get('b2_bucket_id', ''),
                (string) $this->settings->get('b2_bucket_name', ''),
            );
        }

        return new LocalObjectStorage($this->localRoot);
    }

    public function forDriver(string $driver): ObjectStorageInterface
    {
        if ($driver === 'backblaze') {
            return new BackblazeB2Storage(
                (string) $this->settings->get('b2_key_id', ''),
                (string) $this->settings->get('b2_application_key', ''),
                (string) $this->settings->get('b2_bucket_id', ''),
                (string) $this->settings->get('b2_bucket_name', ''),
            );
        }

        return new LocalObjectStorage($this->localRoot);
    }

    public function localAbsolute(string $relativeKey): string
    {
        $local = new LocalObjectStorage($this->localRoot);
        return $local->absolutePath($relativeKey);
    }

    private function currentStokvelId(): ?int
    {
        try {
            $id = app()->container()->get(TenantContext::class)->stokvelId();
            return $id !== null && $id > 0 ? $id : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
