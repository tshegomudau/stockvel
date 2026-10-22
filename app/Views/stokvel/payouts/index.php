<?= view('partials/page-header', [
    'title' => 'Payouts',
    'description' => 'Rotational payout cycles, schedules, and distributions.',
    'actionUrl' => url('/app/payouts/create'),
    'actionLabel' => 'New cycle',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/payouts/categories')) ?>">Manage payout categories</a></p>
<?php $list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No payout cycles', 'emptyActionUrl' => url('/app/payouts/create'), 'emptyActionLabel' => 'Create cycle'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Name</th><th>Category</th><th>Status</th><th>Period</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['name'] ?? $row['cycle_code'] ?? '') ?></td>
<td><?= e($row['category_name'] ?? '—') ?></td>
<td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
<td><?= e(sa_date($row['start_date'] ?? '')) ?> – <?= e(sa_date($row['end_date'] ?? '')) ?></td>
<td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/payouts/' . ($row['id'] ?? ''))) ?>">Open</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
