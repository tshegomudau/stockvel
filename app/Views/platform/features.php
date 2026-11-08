<?= view('partials/page-header', [
    'title' => 'Platform settings',
    'description' => 'Global storage credentials, default editor flags, and password requirements for registration and resets.',
], null) ?>
<?php $s = $settings ?? []; ?>
<form method="post" action="<?= e(url('/platform/features')) ?>" class="form-stack card">
    <?= csrf_field() ?>

    <h2 class="h3">Password requirements</h2>
    <p class="form-help">Applied when users register, reset a password, claim a stokvel, or change their password.</p>
    <div class="form-group">
        <label class="form-label" for="password_min_length">Minimum length</label>
        <input class="form-control" type="number" id="password_min_length" name="password_min_length" min="6" max="128" value="<?= e((string) ($s['password_min_length'] ?? 8)) ?>">
    </div>
    <div class="form-group">
        <label class="form-check"><input type="checkbox" name="password_require_upper" value="1"<?= !empty($s['password_require_upper']) ? ' checked' : '' ?>> Require uppercase letter</label>
    </div>
    <div class="form-group">
        <label class="form-check"><input type="checkbox" name="password_require_lower" value="1"<?= !empty($s['password_require_lower']) ? ' checked' : '' ?>> Require lowercase letter</label>
    </div>
    <div class="form-group">
        <label class="form-check"><input type="checkbox" name="password_require_number" value="1"<?= !empty($s['password_require_number']) ? ' checked' : '' ?>> Require number</label>
    </div>
    <div class="form-group">
        <label class="form-check"><input type="checkbox" name="password_require_special" value="1"<?= !empty($s['password_require_special']) ? ' checked' : '' ?>> Require special character</label>
    </div>

    <h2 class="h3">File storage</h2>
    <div class="form-group">
        <label class="form-check">
            <input type="checkbox" name="file_uploads_enabled" value="1"<?= !empty($s['file_uploads_enabled']) ? ' checked' : '' ?>>
            Enable file uploads (documents, ID copies, media)
        </label>
        <p class="form-help">When off, members and admins can only use typed documents.</p>
    </div>
    <div class="form-group">
        <label class="form-label" for="storage_driver">Storage driver</label>
        <select class="form-control form-select" id="storage_driver" name="storage_driver">
            <option value="local"<?= ($s['storage_driver'] ?? 'local') === 'local' ? ' selected' : '' ?>>Local disk (storage/documents)</option>
            <option value="backblaze"<?= ($s['storage_driver'] ?? '') === 'backblaze' ? ' selected' : '' ?>>Backblaze B2</option>
        </select>
    </div>

    <fieldset class="form-stack" style="border:0;padding:0;margin:0">
        <legend class="h3">Backblaze B2</legend>
        <p class="form-help">Create an application key in the B2 console with write access to your bucket. Bucket ID is shown on the bucket details page.</p>
        <div class="form-grid two-col">
            <div class="form-group">
                <label class="form-label" for="b2_key_id">Key ID</label>
                <input class="form-control" type="text" id="b2_key_id" name="b2_key_id" value="<?= e((string) ($s['b2_key_id'] ?? '')) ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label" for="b2_application_key">Application key</label>
                <input class="form-control" id="b2_application_key" name="b2_application_key" type="password" value="" placeholder="<?= !empty($s['b2_application_key']) ? '•••• saved — leave blank to keep' : '' ?>" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label class="form-label" for="b2_bucket_id">Bucket ID</label>
                <input class="form-control" type="text" id="b2_bucket_id" name="b2_bucket_id" value="<?= e((string) ($s['b2_bucket_id'] ?? '')) ?>" autocomplete="off">
            </div>
            <div class="form-group">
                <label class="form-label" for="b2_bucket_name">Bucket name</label>
                <input class="form-control" type="text" id="b2_bucket_name" name="b2_bucket_name" value="<?= e((string) ($s['b2_bucket_name'] ?? '')) ?>" autocomplete="off">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="b2_endpoint">Endpoint (optional)</label>
            <input class="form-control" type="text" id="b2_endpoint" name="b2_endpoint" value="<?= e((string) ($s['b2_endpoint'] ?? '')) ?>" placeholder="Leave blank for default B2 API">
        </div>
    </fieldset>

    <h2 class="h3">Rich text (WYSIWYG)</h2>
    <div class="form-group">
        <label class="form-check">
            <input type="checkbox" name="wysiwyg_full_enabled" value="1"<?= !empty($s['wysiwyg_full_enabled']) ? ' checked' : '' ?>>
            Enable full WYSIWYG toolbar
        </label>
        <p class="form-help">Adds alignment, indent, subscript/superscript, and code blocks. Basic headings/lists/links always work.</p>
    </div>
    <div class="form-group">
        <label class="form-check">
            <input type="checkbox" name="wysiwyg_images_enabled" value="1"<?= !empty($s['wysiwyg_images_enabled']) ? ' checked' : '' ?>>
            Allow images in the editor
        </label>
        <p class="form-help">Requires full WYSIWYG. Image file insert also needs file uploads enabled.</p>
    </div>
    <div class="form-group">
        <label class="form-check">
            <input type="checkbox" name="wysiwyg_tables_enabled" value="1"<?= !empty($s['wysiwyg_tables_enabled']) ? ' checked' : '' ?>>
            Allow tables
        </label>
    </div>
    <div class="form-group">
        <label class="form-check">
            <input type="checkbox" name="wysiwyg_colors_enabled" value="1"<?= !empty($s['wysiwyg_colors_enabled']) ? ' checked' : '' ?>>
            Allow text and highlight colours
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save settings</button>
    </div>
</form>
