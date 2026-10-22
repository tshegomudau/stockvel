<?= view('partials/page-header', [
    'title' => 'Roles',
    'description' => 'Global role templates (platform + app). Stokvel admins assign these roles to members under Settings → Roles; platform does not edit ordinary users.',
    'actionUrl' => url('/platform/roles/create'),
    'actionLabel' => 'New role',
], null) ?>
<?php if (empty($roles)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No roles', 'emptyMessage' => 'Create a global role template.'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Code</th><th>Name</th><th>Permissions</th><th>System</th><th></th></tr></thead>
<tbody>
<?php foreach ($roles as $role): ?>
<tr>
    <td><code><?= e($role['code'] ?? '') ?></code></td>
    <td><?= e($role['name'] ?? '') ?></td>
    <td><?= (int) ($role['permission_count'] ?? 0) ?></td>
    <td><?= !empty($role['is_system']) ? 'Yes' : 'No' ?></td>
    <td><a class="btn btn-sm btn-secondary" href="<?= e(url('/platform/roles/' . (int) $role['id'] . '/edit')) ?>">Edit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
