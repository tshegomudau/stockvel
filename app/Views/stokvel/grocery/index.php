<?= view('partials/page-header', [
    'title' => 'Grocery cycles',
    'description' => 'Plan bulk grocery / hamper buys — common for December grocery stokvels.',
    'actionUrl' => url('/app/grocery/create'),
    'actionLabel' => 'New cycle',
], null) ?>
<?php $list = isset($paginator) ? $paginator->items : []; ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No grocery cycles', 'emptyActionUrl' => url('/app/grocery/create'), 'emptyActionLabel' => 'Create cycle'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Title</th><th>Target</th><th>Distribution</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['title'] ?? '') ?></td>
<td><?= e(($row['target_amount'] ?? null) !== null ? money($row['target_amount']) : '—') ?></td>
<td><?= e(sa_date($row['distribution_date'] ?? '')) ?></td>
<td><?= e($row['status'] ?? '') ?></td>
<td><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/grocery/' . (int) $row['id'])) ?>">Open</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
