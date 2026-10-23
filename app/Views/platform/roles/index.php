<?= view('partials/page-header', [
    'title' => 'Roles',
    'description' => 'Global role templates. Choose platform (staff) or user (stokvel member) when creating. Stokvel admins assign user roles under Settings → Roles.',
    'actionUrl' => url('/platform/roles/create'),
    'actionLabel' => 'New role',
], null) ?>

<form method="get" class="filter-bar" role="search">
    <div class="form-group">
        <label for="scope">Category</label>
        <select id="scope" name="scope">
            <option value="">All categories</option>
            <option value="platform"<?= ($scopeFilter ?? '') === 'platform' ? ' selected' : '' ?>>Platform</option>
            <option value="user"<?= ($scopeFilter ?? '') === 'user' ? ' selected' : '' ?>>User (stokvel)</option>
        </select>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Apply</button>
    </div>
</form>

<?php if (empty($roles)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No roles', 'emptyMessage' => 'Create a global role template.'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Category</th><th>Code</th><th>Name</th><th>Permissions</th><th>System</th><th></th></tr></thead>
<tbody>
<?php foreach ($roles as $role): ?>
<?php
    $scope = (string) ($role['scope'] ?? 'user');
    $scopeLabel = $scope === 'platform' ? 'Platform' : 'User';
    $scopeBadge = $scope === 'platform' ? 'badge-warning' : 'badge-success';
?>
<tr>
    <td><span class="badge <?= e($scopeBadge) ?>"><?= e($scopeLabel) ?></span></td>
    <td><code><?= e($role['code'] ?? '') ?></code></td>
    <td><?= e($role['name'] ?? '') ?></td>
    <td><?= (int) ($role['permission_count'] ?? 0) ?></td>
    <td><?= !empty($role['is_system']) ? 'Yes' : 'No' ?></td>
    <td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/platform/roles/' . (int) $role['id'] . '/edit')) ?>">Edit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
