<?= view('partials/page-header', [
    'title' => 'Loans',
    'description' => 'Optional lending products, applications, and repayments. Obtain legal/regulatory advice before enabling lending.',
    'actionUrl' => url('/app/loans/create'),
    'actionLabel' => 'New application',
], null) ?>
<div class="alert alert-warning">Lending features require human committee approval. This software does not make automated credit decisions.</div>
<?php $list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No loans yet', 'emptyActionUrl' => url('/app/loans/create'), 'emptyActionLabel' => 'Start application'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Reference</th><th>Principal</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['loan_ref'] ?? $row['reference'] ?? ('#'.$row['id'])) ?></td>
<td class="money"><?= e(money($row['principal_amount'] ?? $row['amount'] ?? 0)) ?></td>
<td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
<td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/loans/' . ($row['id'] ?? ''))) ?>">View</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
