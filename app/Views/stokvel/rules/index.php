<?= view('partials/page-header', [
    'title' => 'Rules & constitution',
    'description' => 'Versioned rules with acknowledgements. Historical active versions are never overwritten.',
    'actionUrl' => url('/app/rules/create'),
    'actionLabel' => 'Add rule',
], null) ?>
<?php $list = $items ?? $rows ?? $rules ?? []; ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No rules published', 'emptyActionUrl' => url('/app/rules/create'), 'emptyActionLabel' => 'Create rule'], null) ?>
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
<?php endif; ?>
