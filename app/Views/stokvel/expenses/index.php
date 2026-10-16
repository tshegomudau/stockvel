<?= view('partials/page-header', [
    'title' => 'Expenses',
    'description' => 'Submit, approve, and track stokvel expenses.',
    'actionUrl' => url('/app/expenses/create'),
    'actionLabel' => 'New expense',
], null) ?>
<?php $list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No expenses recorded', 'emptyActionUrl' => url('/app/expenses/create'), 'emptyActionLabel' => 'Record expense'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Description</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['description'] ?? $row['title'] ?? '') ?></td>
<td class="money"><?= e(money($row['amount'] ?? 0)) ?></td>
<td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
<td><?= e(sa_date($row['expense_date'] ?? $row['created_at'] ?? '')) ?></td>
<td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/expenses/' . ($row['id'] ?? ''))) ?>">View</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
