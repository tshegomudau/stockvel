<?php
$item = $item ?? null;
$isEdit = $item !== null;
$selectedRoles = $selectedRoles ?? [];
$roleOptions = $roleOptions ?? [];
$audienceMode = (string) old('audience_mode', $selectedRoles !== [] ? 'roles' : 'all');
$oldRoles = old('audience_roles', $selectedRoles);
if (!is_array($oldRoles)) {
    $oldRoles = [];
}
$scheduleDefault = '';
if (!empty($item['publish_at']) && ($item['status'] ?? '') === 'scheduled') {
    $scheduleDefault = date('Y-m-d\TH:i', strtotime((string) $item['publish_at']));
}
$expireDefault = !empty($item['expire_at']) ? date('Y-m-d\TH:i', strtotime((string) $item['expire_at'])) : '';
?>
<?= view('partials/page-header', [
    'title' => $isEdit ? 'Edit announcement' : 'New announcement',
    'description' => 'Target roles, save drafts, publish now, or schedule for later.',
], null) ?>
<form method="post"
      action="<?= e($isEdit ? url('/app/announcements/' . (int) $item['id']) : url('/app/announcements')) ?>"
      class="form-stack card">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <?= method_field('PUT') ?>
    <?php endif; ?>
    <div class="form-group">
        <label class="form-label" for="title">Title</label>
        <input id="title" name="title" required value="<?= e(old('title', (string) ($item['title'] ?? ''))) ?>">
    </div>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="priority">Priority</label>
            <select id="priority" name="priority">
                <?php foreach (['low', 'normal', 'high', 'urgent'] as $p): ?>
                    <option value="<?= e($p) ?>"<?= old('priority', (string) ($item['priority'] ?? 'normal')) === $p ? ' selected' : '' ?>><?= e(ucfirst($p)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="audience_mode">Audience</label>
            <select id="audience_mode" name="audience_mode">
                <option value="all"<?= $audienceMode === 'all' ? ' selected' : '' ?>>Everyone</option>
                <option value="roles"<?= $audienceMode === 'roles' ? ' selected' : '' ?>>Selected roles</option>
            </select>
        </div>
    </div>
    <div class="form-group" id="audience-roles-wrap" <?= $audienceMode === 'roles' ? '' : 'hidden' ?>>
        <label class="form-label" for="audience_roles">Roles</label>
        <select id="audience_roles" name="audience_roles[]" multiple size="<?= max(4, min(8, count($roleOptions) ?: 4)) ?>">
            <?php foreach ($roleOptions as $role): ?>
                <?php $code = (string) ($role['code'] ?? ''); ?>
                <option value="<?= e($code) ?>"<?= in_array($code, array_map('strval', $oldRoles), true) ? ' selected' : '' ?>>
                    <?= e((string) ($role['name'] ?? $code)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="form-help">Hold Ctrl/Cmd to select multiple roles. Members with any selected role will see the announcement.</p>
    </div>
    <div class="form-group">
        <label class="form-label" for="body_html">Body</label>
        <textarea id="body_html" name="body_html" rows="12" class="wysiwyg wysiwyg--tall" data-wysiwyg data-wysiwyg-size="tall" aria-label="Announcement body"><?= e(old('body_html', (string) ($item['body_html'] ?? ''))) ?></textarea>
    </div>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="schedule_at">Schedule publish at (optional)</label>
            <input id="schedule_at" name="schedule_at" type="datetime-local"
                   value="<?= e(old('schedule_at', $scheduleDefault)) ?>">
            <p class="form-help">Used when you choose Schedule. Leave blank to save as draft or publish now.</p>
        </div>
        <div class="form-group">
            <label class="form-label" for="expire_at">Expire at (optional)</label>
            <input id="expire_at" name="expire_at" type="datetime-local"
                   value="<?= e(old('expire_at', $expireDefault)) ?>">
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary" name="action" value="draft">Save draft</button>
        <button type="submit" class="btn btn-secondary" name="action" value="schedule">Schedule</button>
        <button type="submit" class="btn btn-primary" name="action" value="publish">Publish now</button>
        <a class="btn btn-ghost" href="<?= e($isEdit ? url('/app/announcements/' . (int) $item['id']) : url('/app/announcements')) ?>">Cancel</a>
    </div>
</form>
<script>
(function () {
    var mode = document.getElementById('audience_mode');
    var wrap = document.getElementById('audience-roles-wrap');
    var roles = document.getElementById('audience_roles');
    if (!mode || !wrap) return;
    function sync() {
        var show = mode.value === 'roles';
        wrap.hidden = !show;
        if (roles) roles.required = show;
    }
    mode.addEventListener('change', sync);
    sync();
})();
</script>
