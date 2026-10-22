<?= view('partials/page-header', [
    'title' => 'Documents',
    'description' => 'Typed constitution, minutes and policies. File uploads arrive in Phase 2 (paid).',
    'actionUrl' => url('/app/documents/create'),
    'actionLabel' => 'New document',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/documents/categories')) ?>">Manage document categories</a></p>

<?php if (!empty($filterCategories)): ?>
<form method="get" action="<?= e(url('/app/documents')) ?>" class="form-stack card mb-6">
    <div class="form-group">
        <label for="category_id">Filter by category</label>
        <select id="category_id" name="category_id" onchange="this.form.submit()">
            <option value="">All categories</option>
            <?php foreach ($filterCategories as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"<?= (int) ($filterCategoryId ?? 0) === (int) $cat['id'] ? ' selected' : '' ?>><?= e($cat['name'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>
<?php endif; ?>

<?php $list = $items ?? $rows ?? $documents ?? []; ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No documents', 'emptyActionUrl' => url('/app/documents/create'), 'emptyActionLabel' => 'Create document'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Title</th><th>Category</th><th>Type</th><th>Updated</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><a href="<?= e(url('/app/documents/' . (int) ($row['id'] ?? 0))) ?>"><?= e($row['title'] ?? $row['original_name'] ?? '') ?></a></td>
<td><?= e($row['category_name'] ?? $row['category'] ?? '') ?></td>
<td><?= e(($row['content_type'] ?? 'file') === 'typed' ? 'Typed' : 'File') ?></td>
<td><?= e(sa_date($row['updated_at'] ?? $row['created_at'] ?? '')) ?></td>
<td class="actions">
    <a class="btn btn-sm btn-secondary" href="<?= e(url('/app/documents/' . (int) ($row['id'] ?? 0))) ?>">Open</a>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
