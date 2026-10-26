<?= view('partials/page-header', [
    'title' => 'Funds',
    'description' => 'Internal accounting pockets. Balances are recorded ledger totals — not bank balances.',
    'actionUrl' => url('/app/funds/create'),
    'actionLabel' => 'New fund',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/settings/categories')) ?>">Category catalogues</a></p>
<?php if (empty($funds)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No funds yet',
        'emptyMessage' => 'Default funds are created automatically. You can also add custom pockets.',
        'emptyActionUrl' => url('/app/funds/create'),
        'emptyActionLabel' => 'Create fund',
    ], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead>
<tr>
    <th>Fund</th>
    <th>Code</th>
    <th>Recorded fund balance</th>
    <th>Credits</th>
    <th>Debits</th>
    <th>Status</th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($funds as $fund): ?>
<tr>
    <td>
        <strong><?= e($fund['name'] ?? '') ?></strong>
        <?php if (!empty($fund['description'])): ?>
            <div class="text-muted"><?= e($fund['description']) ?></div>
        <?php endif; ?>
    </td>
    <td><code><?= e($fund['code'] ?? '') ?></code></td>
    <td class="money"><?= e(money($fund['recorded_balance'] ?? 0)) ?></td>
    <td class="money"><?= e(money($fund['collected'] ?? 0)) ?></td>
    <td class="money"><?= e(money($fund['spent'] ?? 0)) ?></td>
    <td><span class="badge"><?= !empty($fund['is_active']) ? 'active' : 'inactive' ?></span></td>
    <td class="actions">
        <a class="btn btn-sm btn-secondary" href="<?= e(url('/app/funds/' . (int) $fund['id'])) ?>">Ledger</a>
        <a class="btn btn-sm btn-primary" href="<?= e(url('/app/funds/' . (int) $fund['id'] . '/credit')) ?>">Credit</a>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/app/funds/' . (int) $fund['id'] . '/debit')) ?>">Debit</a>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
