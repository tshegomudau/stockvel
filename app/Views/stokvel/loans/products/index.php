<?= view('partials/page-header', [
    'title' => 'Loan products',
    'description' => 'Define amount limits, interest, and terms members can apply for.',
    'actionUrl' => url('/app/loans/products/create'),
    'actionLabel' => 'New product',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/loans')) ?>">&larr; Back to applications</a></p>
<form method="get" action="<?= e(url('/app/loans/products')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Name or code">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterQ ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/loans/products')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<?php $list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No loan products match',
        'emptyMessage' => 'Try clearing filters, or create a product before members can apply for loans.',
        'emptyActionUrl' => url('/app/loans/products/create'),
        'emptyActionLabel' => 'Create product',
    ], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Name</th><th>Amount range</th><th>Rate</th><th>Term</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['name'] ?? '') ?> <small class="text-muted"><?= e($row['code'] ?? '') ?></small></td>
<td class="money"><?= e(money($row['min_amount'] ?? 0)) ?> – <?= e(money($row['max_amount'] ?? 0)) ?></td>
<td><?= e((string) ($row['annual_interest_rate_percent'] ?? $row['interest_rate_percent'] ?? '0')) ?>%</td>
<td><?= e((string) ($row['min_term_months'] ?? 1)) ?>–<?= e((string) ($row['max_term_months'] ?? '')) ?> mo</td>
<td><span class="badge"><?= e(!empty($row['is_active']) ? 'active' : ($row['status'] ?? 'inactive')) ?></span></td>
<td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/loans/products/' . ($row['id'] ?? '') . '/edit')) ?>">Edit</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
