<?= view('partials/page-header', [
    'title' => 'Stokvel types',
    'description' => 'Catalogue of types stokvels can choose in their own settings. Platform manages the list; each stokvel sets its type.',
    'actionUrl' => !empty($canManage) ? url('/platform/stokvel-types/create') : null,
    'actionLabel' => !empty($canManage) ? 'New type' : null,
], null) ?>

<?php if (empty($types)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No types',
        'emptyMessage' => 'Create the first stokvel type for the catalogue.',
    ], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Code</th><th>Name</th><th>Sort</th><th>Active</th><th></th></tr></thead>
<tbody>
<?php foreach ($types as $type): ?>
<tr>
    <td><code><?= e($type['code'] ?? '') ?></code></td>
    <td>
        <?= e($type['name'] ?? '') ?>
        <?php if (!empty($type['description'])): ?>
            <div class="text-muted"><?= e($type['description']) ?></div>
        <?php endif; ?>
    </td>
    <td><?= (int) ($type['sort_order'] ?? 0) ?></td>
    <td><?= !empty($type['is_active']) ? 'Yes' : 'No' ?></td>
    <td>
        <?php if (!empty($type['id'])): ?>
            <a class="btn btn-sm btn-secondary" href="<?= e(url('/platform/stokvel-types/' . (int) $type['id'] . '/edit')) ?>">Edit</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
