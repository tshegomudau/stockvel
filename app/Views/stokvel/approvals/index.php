<?= view('partials/page-header', [
    'title' => 'Approvals',
    'description' => 'Maker-checker queue for financial and governance decisions.',
], null) ?>
<?php
$list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? $approvals ?? []);
$statusFilter = $statusFilter ?? 'pending';
?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No pending approvals', 'emptyMessage' => 'Items requiring committee approval will appear here.'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Type</th><th>Reference</th><th>Requested</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['request_type'] ?? '') ?></td>
<td><?= e(($row['reference_type'] ?? '') . ' #' . ($row['reference_id'] ?? '')) ?></td>
<td><?= e(sa_datetime($row['requested_at'] ?? $row['created_at'] ?? '')) ?></td>
<td><span class="badge badge-warning"><?= e($row['status'] ?? '') ?></span></td>
<td class="actions">
<form method="post" action="<?= e(url('/app/approvals/' . ($row['id'] ?? '') . '/approve')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit">Approve</button></form>
<form method="post" action="<?= e(url('/app/approvals/' . ($row['id'] ?? '') . '/reject')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn btn-sm btn-secondary" type="submit" data-confirm="Reject this request?">Reject</button></form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
