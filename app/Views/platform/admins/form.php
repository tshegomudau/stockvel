<?php
$admin = $adminUser ?? null;
$isEdit = !empty($admin['id']);
$canManage = $canManage ?? true;
?>
<?= view('partials/page-header', [
    'title' => $isEdit ? 'Edit platform admin' : 'Add platform admin',
], null) ?>
<form method="post" action="<?= e(url($isEdit ? '/platform/admins/' . (int) $admin['id'] : '/platform/admins')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-grid two-col">
        <div class="form-group"><label class="form-label" for="first_name">First name</label>
            <input class="form-control" type="text" id="first_name" name="first_name" value="<?= e((string) old('first_name', $admin['first_name'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>></div>
        <div class="form-group"><label class="form-label" for="last_name">Last name</label>
            <input class="form-control" type="text" id="last_name" name="last_name" value="<?= e((string) old('last_name', $admin['last_name'] ?? '')) ?>" <?= $canManage ? '' : 'readonly' ?>></div>
    </div>
    <?php if (!$isEdit): ?>
    <div class="form-group"><label class="form-label required" for="email">Email</label>
        <input class="form-control" type="email" id="email" name="email" required value="<?= e((string) old('email')) ?>"></div>
    <div class="form-group"><label class="form-label required" for="password">Password</label>
        <input class="form-control" type="password" id="password" name="password" required minlength="8"></div>
    <?php else: ?>
    <p class="text-muted">Email: <?= e((string) ($admin['email'] ?? '')) ?></p>
    <div class="form-group"><label class="form-label" for="password">New password (optional)</label>
        <input class="form-control" type="password" id="password" name="password" minlength="8" <?= $canManage ? '' : 'disabled' ?>></div>
    <div class="form-group"><label class="form-label" for="status">Status</label>
        <select class="form-control form-select" id="status" name="status" <?= $canManage ? '' : 'disabled' ?>>
            <?php foreach (['active','inactive','suspended'] as $st): ?>
                <option value="<?= $st ?>"<?= ($admin['status'] ?? '') === $st ? ' selected' : '' ?>><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <div class="form-group"><label class="form-label" for="platform_role">Platform role</label>
        <select class="form-control form-select" id="platform_role" name="platform_role" <?= $canManage ? '' : 'disabled' ?>>
            <?php foreach (($roles ?? []) as $role): ?>
                <option value="<?= e($role) ?>"<?= (string) old('platform_role', $admin['platform_role'] ?? '') === $role ? ' selected' : '' ?>><?= e($role) ?></option>
            <?php endforeach; ?>
        </select></div>
    <fieldset>
        <legend class="h3">Additional permissions</legend>
        <p class="form-help">You can only grant permissions you hold. Super admins already have all access via role.</p>
        <?php foreach (($permissionCatalog ?? []) as $code): ?>
            <label class="form-check">
                <input type="checkbox" name="permissions[]" value="<?= e($code) ?>"
                    <?= in_array($code, $grantedOverrides ?? [], true) ? 'checked' : '' ?>
                    <?= $canManage ? '' : 'disabled' ?>>
                <?= e(($permissionLabels[$code] ?? $code)) ?>
            </label>
        <?php endforeach; ?>
    </fieldset>
    <?php if ($canManage): ?>
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save' : 'Create' ?></button>
    <?php endif; ?>
</form>
