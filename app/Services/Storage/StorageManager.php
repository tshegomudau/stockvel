<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Exceptions\HttpException;
use App\Services\Platform\PlatformSettingsService;

final class StorageManager
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly string $localRoot,
    ) {
    }

    public function uploadsEnabled(): bool
    {
        return $this->settings->enabled('file_uploads_enabled');
    }

    public function assertUploadsEnabled(): void
    {
        if (!$this->uploadsEnabled()) {
            throw new HttpException(
                403,
                'File uploads are disabled. Enable them under Platform → Features & storage.'
            );
        }
    }

    public function driver(): ObjectStorageInterface
    {
        $this->assertUploadsEnabled();
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
}
