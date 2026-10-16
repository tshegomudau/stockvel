<?= view('partials/page-header', [
    'title' => 'Fee structures',
    'description' => 'Configure joining fees, recurring contributions, and penalties.',
    'actionUrl' => url('/app/fees/create'),
    'actionLabel' => 'New fee structure',
], null) ?>
<?php $list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No fee structures', 'emptyActionUrl' => url('/app/fees/create'), 'emptyActionLabel' => 'Create fee structure'], null) ?>
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
