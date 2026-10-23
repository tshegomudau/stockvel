<?= view('partials/page-header', [
    'title' => 'Expenses',
    'description' => 'Submit, approve, and track stokvel expenses.',
    'actionUrl' => url('/app/expenses/create'),
    'actionLabel' => 'New expense',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/expenses/categories')) ?>">Manage expense categories</a></p>
<form method="get" action="<?= e(url('/app/expenses')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Description, vendor, or ref">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach ($statusOptions ?? ['requested', 'approved', 'rejected', 'paid', 'reversed'] as $st): ?>
                <option value="<?= e($st) ?>"<?= ($filterStatus ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="category_id">Category</label>
        <select id="category_id" name="category_id">
            <option value="">All categories</option>
            <?php foreach ($filterCategories ?? [] as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"<?= (int) ($filterCategoryId ?? 0) === (int) $cat['id'] ? ' selected' : '' ?>><?= e($cat['name'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterQ ?? '') !== '' || ($filterStatus ?? '') !== '' || (int) ($filterCategoryId ?? 0) > 0): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/expenses')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<?php $list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No expenses match', 'emptyActionUrl' => url('/app/expenses/create'), 'emptyActionLabel' => 'Record expense'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Description</th><th>Category</th><th>Amount</th><th>Status</th><th>Date</th><th>Captured by</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['description'] ?? $row['title'] ?? '') ?></td>
<td><?= e($row['category_name'] ?? '—') ?></td>
<td class="money"><?= e(money($row['amount'] ?? 0)) ?></td>
<td><span class="badge"><?= e(ucfirst((string) ($row['status'] ?? ''))) ?></span></td>
<td><?= e(sa_date($row['incurred_at'] ?? $row['expense_date'] ?? $row['created_at'] ?? '')) ?></td>
<td><?= e(trim((string) ($row['captured_by_name'] ?? '')) !== '' ? $row['captured_by_name'] : '—') ?></td>
<td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/expenses/' . ($row['id'] ?? ''))) ?>">View</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
