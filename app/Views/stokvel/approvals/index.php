<?= view('partials/page-header', [
    'title' => 'My Approvals',
    'description' => 'Independent multi-approver decisions. Overall status stays pending until required approvals are met. Rejection requires a reason.',
], null) ?>
<?php
$list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? $approvals ?? []);
$statusFilter = $statusFilter ?? 'pending';
$decisionMap = $decisionMap ?? [];
?>
<?php if (can('settings.manage') || can('approvals.action')): ?>
<p class="mb-4"><a class="btn btn-secondary btn-sm" href="<?= e(route('app.approval_policies')) ?>">Manage thresholds</a></p>
<?php endif; ?>
<nav class="filter-bar mb-4" aria-label="Approval status">
    <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label): ?>
        <a class="btn btn-sm <?= $statusFilter === $key ? 'btn-primary' : 'btn-secondary' ?>"
           href="<?= e(url('/app/approvals?status=' . $key)) ?>"<?= $statusFilter === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No approvals', 'emptyMessage' => 'Items requiring committee approval will appear here.'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead>
<tr>
    <th scope="col">Type</th>
    <th scope="col">Reference</th>
    <th scope="col">Amount</th>
    <th scope="col">Requested by</th>
    <th scope="col">When</th>
    <th scope="col">Approvals</th>
    <th scope="col">Timeline</th>
    <th scope="col">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($list as $row): ?>
<?php
    $rtype = (string) ($row['request_type'] ?? $row['reference_type'] ?? '');
    $labels = [
        'loan_application' => 'Loan application',
        'member_change' => 'Member change (awaiting member)',
        'member_change_request' => 'Member change (awaiting member)',
        'burial_claim' => 'Burial claim',
        'meeting_notes' => 'Meeting notes',
        'expense' => 'Expense',
        'payout' => 'Payout',
        'adjustment' => 'Financial adjustment',
    ];
    $typeLabel = $labels[$rtype] ?? ucwords(str_replace('_', ' ', $rtype));
    $reqId = (int) ($row['id'] ?? 0);
    $decisions = $decisionMap[$reqId] ?? [];
    $required = (int) ($row['required_approvals'] ?? 1);
    $count = (int) ($row['approval_count'] ?? count(array_filter($decisions, static fn ($d) => ($d['decision'] ?? '') === 'approved')));
?>
<tr>
<td><?= e($typeLabel) ?></td>
<td><?= e(($row['reference_type'] ?? '') . ' #' . ($row['reference_id'] ?? '')) ?>
    <?php if (in_array($rtype, ['member_change', 'member_change_request'], true)): ?>
        <div class="text-sm text-muted">Member must approve in their portal</div>
    <?php endif; ?>
</td>
<td class="money"><?= isset($row['amount']) && $row['amount'] !== null && $row['amount'] !== '' ? e(money($row['amount'])) : '—' ?></td>
<td><?= e(trim((string) ($row['requested_by_name'] ?? '')) ?: ($row['requested_by_email'] ?? '—')) ?></td>
<td><?= e(sa_datetime($row['requested_at'] ?? $row['created_at'] ?? '')) ?></td>
<td>
    <span class="badge"><?= e((string) ($row['status'] ?? '')) ?></span>
    <div class="text-sm"><?= $count ?> / <?= $required ?> approvals</div>
</td>
<td>
    <ol class="plain-list text-sm">
        <li>Requested</li>
        <?php foreach ($decisions as $d): ?>
            <li><?= e(trim((string) ($d['actor_name'] ?? '')) ?: ($d['actor_email'] ?? 'Approver')) ?>:
                <?= e(strtoupper((string) ($d['decision'] ?? ''))) ?>
                <?php if (!empty($d['reason'])): ?> — <?= e((string) $d['reason']) ?><?php endif; ?>
            </li>
        <?php endforeach; ?>
        <?php if (($row['status'] ?? '') === 'pending'): ?>
            <li>Overall: PENDING</li>
        <?php else: ?>
            <li>Overall: <?= e(strtoupper((string) $row['status'])) ?></li>
        <?php endif; ?>
    </ol>
</td>
<td class="actions">
<?php if (($row['status'] ?? '') === 'pending'): ?>
<form method="post" action="<?= e(url('/app/approvals/' . $reqId . '/approve')) ?>" class="form-stack" style="display:inline-block;min-width:12rem;">
    <?= csrf_field() ?>
    <label class="sr-only" for="approve-notes-<?= $reqId ?>">Approval notes</label>
    <input id="approve-notes-<?= $reqId ?>" name="notes" placeholder="Optional notes">
    <button class="btn btn-sm btn-primary" type="submit">Approve</button>
</form>
<form method="post" action="<?= e(url('/app/approvals/' . $reqId . '/reject')) ?>" class="form-stack" style="display:inline-block;min-width:12rem;">
    <?= csrf_field() ?>
    <label class="sr-only" for="reject-reason-<?= $reqId ?>">Rejection reason</label>
    <input id="reject-reason-<?= $reqId ?>" name="reason" required placeholder="Rejection reason (required)">
    <button class="btn btn-sm btn-secondary" type="submit" data-confirm="Reject this request?">Reject</button>
</form>
<?php else: ?>
—
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
