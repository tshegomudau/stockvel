<?= view('partials/page-header', [
    'title' => 'Rules & constitution',
    'description' => 'Versioned rules with acknowledgements. Historical active versions are never overwritten.',
    'actionUrl' => url('/app/rules/create'),
    'actionLabel' => 'Add rule',
], null) ?>
<form method="get" action="<?= e(url('/app/rules')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Title or code">
    </div>
    <div class="form-group">
        <label for="category">Category</label>
        <select id="category" name="category">
            <option value="">All</option>
            <?php foreach ($categoryOptions ?? [] as $code => $label): ?>
                <option value="<?= e((string) $code) ?>"<?= ($filterCategory ?? '') === (string) $code ? ' selected' : '' ?>><?= e((string) $label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach ($statusOptions ?? ['active', 'inactive'] as $st): ?>
                <option value="<?= e($st) ?>"<?= ($filterStatus ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterQ ?? '') !== '' || ($filterStatus ?? '') !== '' || ($filterCategory ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/rules')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<?php $list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? $rules ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No rules match', 'emptyActionUrl' => url('/app/rules/create'), 'emptyActionLabel' => 'Create rule'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Version</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['title'] ?? '') ?></td>
<td><?= e($row['category'] ?? '') ?></td>
<td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
<td><?= e((string) ($row['current_version'] ?? '—')) ?></td>
<td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/rules/' . ($row['id'] ?? ''))) ?>">View</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
