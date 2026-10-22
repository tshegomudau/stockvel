<?= view('partials/page-header', [
    'title' => $type ? 'Edit stokvel type' : 'New stokvel type',
    'description' => 'Types appear in stokvel Settings and onboarding for each group to choose.',
], null) ?>
<?php
$isEdit = !empty($type['id']);
$canManage = $canManage ?? true;
?>
<?php if (!$canManage && $isEdit): ?>
<div class="card">
    <dl class="detail-list">
        <dt>Code</dt><dd><code><?= e($type['code'] ?? '') ?></code></dd>
        <dt>Name</dt><dd><?= e($type['name'] ?? '') ?></dd>
        <dt>Description</dt><dd><?= e($type['description'] ?? '—') ?></dd>
        <dt>Sort</dt><dd><?= (int) ($type['sort_order'] ?? 0) ?></dd>
        <dt>Active</dt><dd><?= !empty($type['is_active']) ? 'Yes' : 'No' ?></dd>
    </dl>
    <a class="btn btn-secondary" href="<?= e(url('/platform/stokvel-types')) ?>">Back</a>
</div>
<?php else: ?>
<form method="post" action="<?= e(url($isEdit ? '/platform/stokvel-types/' . (int) $type['id'] : '/platform/stokvel-types')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <?php if (!$isEdit): ?>
        <div class="form-group">
            <label class="form-label" for="code">Code</label>
            <input id="code" name="code" required pattern="[a-z0-9_]+" placeholder="e.g. burial_society">
            <p class="form-help">Lowercase letters, numbers, underscores. Stored on each stokvel; cannot change later.</p>
        </div>
    <?php else: ?>
        <p class="text-muted">Code: <code><?= e($type['code'] ?? '') ?></code></p>
    <?php endif; ?>
    <div class="form-group">
        <label class="form-label" for="name">Name</label>
        <input id="name" name="name" required value="<?= e($type['name'] ?? '') ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" name="description" rows="2"><?= e($type['description'] ?? '') ?></textarea>
    </div>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="sort_order">Sort order</label>
            <input id="sort_order" name="sort_order" type="number" value="<?= e((string) ($type['sort_order'] ?? '0')) ?>">
        </div>
        <div class="form-group">
            <label class="checkbox" style="margin-top:1.75rem">
                <input type="checkbox" name="is_active" value="1"<?= ($type === null || !empty($type['is_active'])) ? ' checked' : '' ?>>
                Active (selectable by stokvels)
            </label>
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save type</button>
        <a class="btn btn-secondary" href="<?= e(url('/platform/stokvel-types')) ?>">Cancel</a>
    </div>
</form>
<?php if ($isEdit): ?>
<form method="post" action="<?= e(url('/platform/stokvel-types/' . (int) $type['id'] . '/delete')) ?>" class="mt-6">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-danger" data-confirm="Delete this type? Only allowed if no stokvel uses it.">Delete type</button>
</form>
<?php endif; ?>
<?php endif; ?>
