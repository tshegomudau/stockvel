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
<thead><tr><th>Type</th><th>Reference</th><th>Requested by</th><th>When</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<?php
    $rtype = (string) ($row['request_type'] ?? $row['reference_type'] ?? '');
    $labels = [
        'loan_application' => 'Loan application',
        'member_change' => 'Member change (awaiting member)',
        'member_change_request' => 'Member change (awaiting member)',
        'burial_claim' => 'Burial claim',
        'meeting_notes' => 'Meeting notes',
    ];
    $typeLabel = $labels[$rtype] ?? ucwords(str_replace('_', ' ', $rtype));
?>
<td><?= e($typeLabel) ?></td>
<td><?= e(($row['reference_type'] ?? '') . ' #' . ($row['reference_id'] ?? '')) ?>
    <?php if (in_array($rtype, ['member_change', 'member_change_request'], true)): ?>
        <div class="text-sm text-muted">Member must approve in their portal</div>
    <?php endif; ?>
</td>
<td><?= e(trim((string) ($row['requested_by_name'] ?? '')) ?: ($row['requested_by_email'] ?? '—')) ?></td>
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
