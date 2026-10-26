<?= view('partials/page-header', [
    'title' => 'My fines',
    'description' => 'Fines issued against your membership.',
], null) ?>
<?php if (empty($fines)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No fines',
        'emptyMessage' => 'You have no fines on record for this stokvel.',
    ], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead>
<tr>
    <th>Type</th>
    <th>Amount</th>
    <th>Paid</th>
    <th>Status</th>
    <th>Issued</th>
    <th>Due</th>
</tr>
</thead>
<tbody>
<?php foreach ($fines as $row): ?>
<tr>
    <td>
        <?= e($row['fine_type_name_snapshot'] ?? $row['fine_type_name'] ?? '') ?>
        <?php if (!empty($row['reason'])): ?>
            <div class="text-muted"><?= e($row['reason']) ?></div>
        <?php endif; ?>
    </td>
    <td class="money"><?= e(money($row['amount'] ?? 0)) ?></td>
    <td class="money"><?= e(money($row['amount_paid'] ?? 0)) ?></td>
    <td><span class="badge"><?= e(str_replace('_', ' ', (string) ($row['status'] ?? ''))) ?></span></td>
    <td><?= e(sa_date($row['issued_at'] ?? '')) ?></td>
    <td><?= e(sa_date($row['due_date'] ?? '') ?: '—') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
