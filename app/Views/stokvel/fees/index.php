<?= view('partials/page-header', [
    'title' => 'Fee structures',
    'description' => 'Configure joining fees, recurring contributions, and penalties.',
    'actionUrl' => url('/app/fees/create'),
    'actionLabel' => 'New fee structure',
], null) ?>
<form method="get" action="<?= e(url('/app/fees')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Name or code">
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
        <?php if (($filterQ ?? '') !== '' || ($filterStatus ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/fees')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<?php $list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No fee structures match', 'emptyActionUrl' => url('/app/fees/create'), 'emptyActionLabel' => 'Create fee structure'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Name</th><th>Type</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['name'] ?? '') ?></td>
<td><?= e($row['structure_type'] ?? $row['type'] ?? '') ?></td>
<td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
<td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/fees/' . ($row['id'] ?? ''))) ?>">View</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
