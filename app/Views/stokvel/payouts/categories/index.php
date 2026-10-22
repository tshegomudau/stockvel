<?= view('partials/page-header', [
    'title' => 'Payout categories',
    'description' => 'Classify payout cycles (burial, grocery, emergency, and custom types).',
    'actionUrl' => url('/app/payouts/categories/create'),
    'actionLabel' => 'New category',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/payouts')) ?>">&larr; Back to payouts</a></p>
<?php if (empty($categories)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No payout categories',
        'emptyMessage' => 'Default categories are created when migration 011 runs.',
        'emptyActionUrl' => url('/app/payouts/categories/create'),
        'emptyActionLabel' => 'Add category',
    ], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Code</th><th>Name</th><th>Description</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($categories as $cat): ?>
<tr>
<td><?= e($cat['code'] ?? '') ?></td>
<td><?= e($cat['name'] ?? '') ?></td>
<td><?= e($cat['description'] ?? '') ?></td>
<td><span class="badge"><?= !empty($cat['is_active']) ? 'active' : 'inactive' ?></span></td>
<td class="actions">
    <a class="btn btn-sm btn-secondary" href="<?= e(url('/app/payouts/categories/' . (int) $cat['id'] . '/edit')) ?>">Edit</a>
    <?php if (!empty($cat['is_active'])): ?>
    <form method="post" action="<?= e(url('/app/payouts/categories/' . (int) $cat['id'] . '/deactivate')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-ghost">Deactivate</button>
    </form>
    <?php else: ?>
    <form method="post" action="<?= e(url('/app/payouts/categories/' . (int) $cat['id'] . '/activate')) ?>" class="inline-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-primary">Activate</button>
    </form>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
