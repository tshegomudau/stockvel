<?= view('partials/page-header', [
    'title' => 'Burial claims',
    'description' => 'Funeral benefit claims for burial society members — committee approval required.',
    'actionUrl' => url('/app/burial-claims/create'),
    'actionLabel' => 'New claim',
], null) ?>
<?php $list = isset($paginator) ? $paginator->items : []; ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No claims', 'emptyActionUrl' => url('/app/burial-claims/create'), 'emptyActionLabel' => 'Submit claim'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Ref</th><th>Member</th><th>Deceased</th><th>Amount</th><th>Status</th><th>Submitted</th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['claim_ref'] ?? '') ?></td>
<td><?= e($row['member_name'] ?? '') ?></td>
<td><?= e($row['deceased_name'] ?? '') ?></td>
<td><?= e(money($row['claimed_amount'] ?? 0)) ?></td>
<td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
<td><?= e(sa_date($row['created_at'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
