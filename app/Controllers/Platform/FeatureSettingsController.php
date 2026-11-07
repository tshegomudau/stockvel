<?php

declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PermissionService;
use App\Services\Platform\PlatformSettingsService;

final class FeatureSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }
        app()->container()->get(PermissionService::class)->requirePlatformPermission('features.manage');

        /** @var PlatformSettingsService $settings */
        $settings = app()->container()->get(PlatformSettingsService::class);

        return $this->render('platform/features', [
            'title' => 'Storage & default editor flags',
            'activeNav' => 'platform-features',
            'navContext' => 'platform',
            'settings' => $settings->all(),
        ]);
    }

    public function update(Request $request): Response
    {
        $this->requireAuth();
        if (!$this->tenant()->isPlatformAdmin()) {
            return $this->redirect('/app');
        }
        app()->container()->get(PermissionService::class)->requirePlatformPermission('features.manage');

        $driver = trim((string) $request->input('storage_driver', 'local'));
        if (!in_array($driver, ['local', 'backblaze'], true)) {
            $driver = 'local';
        }

        $payload = [
            'file_uploads_enabled' => $request->input('file_uploads_enabled') ? true : false,
            'storage_driver' => $driver,
            'b2_key_id' => (string) $request->input('b2_key_id', ''),
            'b2_application_key' => (string) $request->input('b2_application_key', ''),
            'b2_bucket_id' => (string) $request->input('b2_bucket_id', ''),
            'b2_bucket_name' => (string) $request->input('b2_bucket_name', ''),
            'b2_endpoint' => (string) $request->input('b2_endpoint', ''),
            'wysiwyg_full_enabled' => $request->input('wysiwyg_full_enabled') ? true : false,
            'wysiwyg_images_enabled' => $request->input('wysiwyg_images_enabled') ? true : false,
            'wysiwyg_tables_enabled' => $request->input('wysiwyg_tables_enabled') ? true : false,
            'wysiwyg_colors_enabled' => $request->input('wysiwyg_colors_enabled') ? true : false,
        ];

        // Keep existing application key when the field is left blank (password-style).
        /** @var PlatformSettingsService $settings */
        $settings = app()->container()->get(PlatformSettingsService::class);
        $current = $settings->all();
        if ($payload['b2_application_key'] === '' && (string) ($current['b2_application_key'] ?? '') !== '') {
            $payload['b2_application_key'] = (string) $current['b2_application_key'];
        }

        if ($payload['file_uploads_enabled'] && $payload['storage_driver'] === 'backblaze') {
            if ($payload['b2_key_id'] === '' || $payload['b2_application_key'] === '' || $payload['b2_bucket_id'] === '' || $payload['b2_bucket_name'] === '') {
                Session::flash('error', 'requires Key ID, Application Key, Bucket ID, and Bucket name.');
                return $this->redirect('/platform/features');
            }
        }

        try {
            $settings->save($payload);
            Session::flash('success', 'Platform features and storage settings saved.');
        } catch (\Throwable $e) {
            Session::flash('error', 'Could not save settings. Run migrations if the platform_settings table is missing.');
        }

        return $this->redirect('/platform/features');
    }
}
