<?= view('partials/page-header', [
    'title' => $role ? 'Edit role' : 'New role',
    'description' => 'Assign the permission set for this global role template.',
], null) ?>
<?php $isEdit = !empty($role['id']); ?>
<form method="post" action="<?= e(url($isEdit ? '/platform/roles/' . (int) $role['id'] : '/platform/roles')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <?php if (!$isEdit): ?>
        <div class="form-group">
            <label class="form-label" for="code">Code</label>
            <input id="code" name="code" required pattern="[a-z0-9_.-]+" placeholder="e.g. burial_officer">
        </div>
    <?php else: ?>
        <p class="text-muted">Code: <code><?= e($role['code'] ?? '') ?></code></p>
    <?php endif; ?>
    <div class="form-group">
        <label class="form-label" for="name">Name</label>
        <input id="name" name="name" required value="<?= e($role['name'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="scope">Role category</label>
        <?php $scope = (string) ($role['scope'] ?? old('scope', 'user')); ?>
        <select id="scope" name="scope" required>
            <option value="user"<?= $scope === 'user' ? ' selected' : '' ?>>User (stokvel member roles)</option>
            <option value="platform"<?= $scope === 'platform' ? ' selected' : '' ?>>Platform (staff roles)</option>
        </select>
        <p class="form-help">Platform roles are for stockvel operators. User roles are assigned to stokvel members.</p>
    </div>
    <div class="form-group">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" name="description" rows="2"><?= e($role['description'] ?? '') ?></textarea>
    </div>
    <fieldset class="form-section">
        <legend>Permissions</legend>
        <div class="permission-grid">
            <?php foreach ($permissions as $perm): ?>
                <?php $pid = (int) $perm['id']; ?>
                <label class="checkbox">
                    <input type="checkbox" name="permissions[]" value="<?= $pid ?>"<?= in_array($pid, $selected ?? [], true) ? ' checked' : '' ?>>
                    <code><?= e($perm['code'] ?? '') ?></code>
                    <?php if (!empty($perm['description'])): ?>
                        <span class="text-muted"><?= e($perm['description']) ?></span>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save role</button>
        <a class="btn btn-secondary" href="<?= e(url('/platform/roles')) ?>">Cancel</a>
    </div>
</form>
<?php if ($isEdit && empty($role['is_system'])): ?>
<form method="post" action="<?= e(url('/platform/roles/' . (int) $role['id'] . '/delete')) ?>" class="mt-6">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-danger" data-confirm="Delete this role permanently?">Delete role</button>
</form>
<?php endif; ?>
