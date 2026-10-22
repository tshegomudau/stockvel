<?= view('partials/page-header', [
    'title' => 'Loans',
    'description' => 'Optional lending products, applications, and repayments. ',
    'actionUrl' => url('/app/loans/create'),
    'actionLabel' => 'New application',
], null) ?>
<div class="alert alert-warning">Lending features require human committee approval.</div>
<p class="text-muted"><a href="<?= e(url('/app/loans/products')) ?>">Manage loan products</a></p>
<?php $list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No loan applications', 'emptyActionUrl' => url('/app/loans/create'), 'emptyActionLabel' => 'Start application'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Reference</th><th>Member</th><th>Product</th><th>Amount</th><th>Term</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['application_ref'] ?? $row['loan_ref'] ?? ('#' . ($row['id'] ?? ''))) ?></td>
<td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
<td><?= e($row['product_name'] ?? '—') ?></td>
<td class="money"><?= e(money($row['requested_amount'] ?? $row['principal_amount'] ?? 0)) ?></td>
<td><?= e((string) ($row['term_months'] ?? '—')) ?><?= isset($row['term_months']) ? ' mo' : '' ?></td>
<td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
<td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/loans/' . ($row['id'] ?? ''))) ?>">View</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
