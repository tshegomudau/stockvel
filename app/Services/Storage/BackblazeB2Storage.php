<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Exceptions\HttpException;
use RuntimeException;

/**
 * Backblaze B2 via native HTTP API (no AWS SDK required).
 */
final class BackblazeB2Storage implements ObjectStorageInterface
{
    /** @var array{authorizationToken:string,apiUrl:string,downloadUrl:string,accountId:string}|null */
    private ?array $auth = null;

    public function __construct(
        private readonly string $keyId,
        private readonly string $applicationKey,
        private readonly string $bucketId,
        private readonly string $bucketName,
    ) {
        if ($keyId === '' || $applicationKey === '' || $bucketId === '') {
            throw new RuntimeException('Storage is not configured. Set credentials in Platform settings.');
        }
    }

    public function putFile(string $localPath, string $key, string $mimeType): array
    {
        $auth = $this->authorize();
        $upload = $this->getUploadUrl($auth);
        $body = file_get_contents($localPath);
        if ($body === false) {
            throw new RuntimeException('Unable to read file for B2 upload.');
        }
        $sha1 = sha1($body);
        $fileName = ltrim($key, '/');

        $ch = curl_init($upload['uploadUrl']);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $upload['authorizationToken'],
                'X-Bz-File-Name: ' . rawurlencode($fileName),
                'Content-Type: ' . ($mimeType !== '' ? $mimeType : 'b2/x-auto'),
                'Content-Length: ' . (string) strlen($body),
                'X-Bz-Content-Sha1: ' . $sha1,
            ],
            CURLOPT_TIMEOUT => 120,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code < 200 || $code >= 300) {
            throw new RuntimeException('B2 upload failed (HTTP ' . $code . ').');
        }

        return [
            'driver' => 'backblaze',
            'path' => $fileName,
            'size' => strlen($body),
        ];
    }

    public function downloadToTemp(string $key): string
    {
        $auth = $this->authorize();
        $url = rtrim($auth['downloadUrl'], '/') . '/file/' . rawurlencode($this->bucketName) . '/' . ltrim($key, '/');
        $tmp = tempnam(sys_get_temp_dir(), 'stovel_b2_');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create temp file.');
        }
        $fp = fopen($tmp, 'wb');
        if ($fp === false) {
            throw new RuntimeException('Unable to open temp file.');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            fclose($fp);
            throw new RuntimeException('curl_init failed.');
        }
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_HTTPHEADER => ['Authorization: ' . $auth['authorizationToken']],
            CURLOPT_TIMEOUT => 120,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if ($ok === false || $code < 200 || $code >= 300) {
            @unlink($tmp);
            throw new HttpException(404, 'File not found.');
        }

        return $tmp;
    }

    public function delete(string $key): void
    {
        // Soft-delete not required for MVP; versions are retained in B2.
    }

    public function exists(string $key): bool
    {
        try {
            $tmp = $this->downloadToTemp($key);
            @unlink($tmp);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function driverName(): string
    {
        return 'backblaze';
    }

    /** @return array{authorizationToken:string,apiUrl:string,downloadUrl:string,accountId:string} */
    private function authorize(): array
    {
        if ($this->auth !== null) {
            return $this->auth;
        }
        $ch = curl_init('https://api.backblazeb2.com/b2api/v2/b2_authorize_account');
        if ($ch === false) {
            throw new RuntimeException('curl_init failed.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->keyId . ':' . $this->applicationKey,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code !== 200) {
            throw new RuntimeException('authorization failed. Check Key ID and Application Key.');
        }
        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $this->auth = [
            'authorizationToken' => (string) $data['authorizationToken'],
            'apiUrl' => (string) $data['apiUrl'],
            'downloadUrl' => (string) $data['downloadUrl'],
            'accountId' => (string) $data['accountId'],
        ];

        return $this->auth;
    }

    /**
     * @param array{authorizationToken:string,apiUrl:string,downloadUrl:string,accountId:string} $auth
     * @return array{uploadUrl:string,authorizationToken:string}
     */
    private function getUploadUrl(array $auth): array
    {
        $url = rtrim($auth['apiUrl'], '/') . '/b2api/v2/b2_get_upload_url';
        $payload = json_encode(['bucketId' => $this->bucketId], JSON_THROW_ON_ERROR);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $auth['authorizationToken'],
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code !== 200) {
            throw new RuntimeException('B2 get_upload_url failed.');
        }
        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return [
            'uploadUrl' => (string) $data['uploadUrl'],
            'authorizationToken' => (string) $data['authorizationToken'],
        ];
    }
}
