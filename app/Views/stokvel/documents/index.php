<?= view('partials/page-header', [
    'title' => 'Documents',
    'description' => 'Secure tenant document vault with controlled downloads.',
    'actionUrl' => url('/app/documents/upload'),
    'actionLabel' => 'Upload',
], null) ?>
<?php $list = $items ?? $rows ?? $documents ?? []; ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No documents', 'emptyActionUrl' => url('/app/documents/upload'), 'emptyActionLabel' => 'Upload document'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Title</th><th>Category</th><th>Updated</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['title'] ?? $row['original_name'] ?? '') ?></td>
<td><?= e($row['category'] ?? '') ?></td>
<td><?= e(sa_date($row['updated_at'] ?? $row['created_at'] ?? '')) ?></td>
<td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/documents/' . ($row['id'] ?? '') . '/download')) ?>">Download</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
