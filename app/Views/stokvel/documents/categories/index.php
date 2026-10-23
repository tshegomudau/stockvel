<?= view('partials/page-header', [
    'title' => 'Document categories',
    'description' => 'Organise typed documents and legacy category labels.',
    'actionUrl' => url('/app/documents/categories/create'),
    'actionLabel' => 'New category',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/documents')) ?>">&larr; Back to documents</a></p>
<form method="get" action="<?= e(url('/app/documents/categories')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Name or code">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterQ ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/documents/categories')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<?php if (empty($categories)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No document categories', 'emptyActionUrl' => url('/app/documents/categories/create'), 'emptyActionLabel' => 'Add category'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Code</th><th>Name</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($categories as $cat): ?>
<tr>
<td><?= e($cat['code'] ?? '') ?></td>
<td><?= e($cat['name'] ?? '') ?></td>
<td><span class="badge"><?= !empty($cat['is_active']) ? 'active' : 'inactive' ?></span></td>
<td class="actions">
    <a class="btn btn-sm btn-secondary" href="<?= e(url('/app/documents/categories/' . (int) $cat['id'] . '/edit')) ?>">Edit</a>
    <?php if (!empty($cat['is_active'])): ?>
    <form method="post" action="<?= e(url('/app/documents/categories/' . (int) $cat['id'] . '/deactivate')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-ghost">Deactivate</button></form>
    <?php else: ?>
    <form method="post" action="<?= e(url('/app/documents/categories/' . (int) $cat['id'] . '/activate')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary">Activate</button></form>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
