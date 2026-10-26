<?= view('partials/page-header', [
    'title' => $fund['name'] ?? 'Fund',
    'description' => 'Ledger for this accounting pocket. Recorded fund balance is credits minus debits.',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/funds')) ?>">&larr; All funds</a></p>

<div class="card mb-6">
    <dl class="detail-list">
        <dt>Code</dt><dd><code><?= e($fund['code'] ?? '') ?></code></dd>
        <dt>Description</dt><dd><?= e($fund['description'] ?? '—') ?></dd>
        <dt>Recorded fund balance</dt><dd class="money"><?= e(money($summary['balance'] ?? 0)) ?></dd>
        <dt>Total credits</dt><dd class="money"><?= e(money($summary['collected'] ?? 0)) ?></dd>
        <dt>Total debits</dt><dd class="money"><?= e(money($summary['spent'] ?? 0)) ?></dd>
        <dt>Status</dt><dd><span class="badge"><?= !empty($fund['is_active']) ? 'active' : 'inactive' ?></span></dd>
    </dl>
    <div class="form-actions mt-4">
        <a class="btn btn-primary" href="<?= e(url('/app/funds/' . (int) $fund['id'] . '/credit')) ?>">Credit</a>
        <a class="btn btn-secondary" href="<?= e(url('/app/funds/' . (int) $fund['id'] . '/debit')) ?>">Debit (expense)</a>
    </div>
</div>

<h2>Ledger</h2>
<?php $list = isset($paginator) ? $paginator->items : []; ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No transactions yet', 'emptyMessage' => 'Credits and debits will appear here.'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead>
<tr>
    <th>Date</th>
    <th>Direction</th>
    <th>Amount</th>
    <th>Balance after</th>
    <th>Description</th>
    <th>Category</th>
    <th>By</th>
</tr>
</thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
    <td><?= e(sa_date($row['entry_date'] ?? '')) ?></td>
    <td><span class="badge"><?= e((string) ($row['direction'] ?? '')) ?></span></td>
    <td class="money"><?= e(money($row['amount'] ?? 0)) ?></td>
    <td class="money"><?= e(money($row['balance_after'] ?? 0)) ?></td>
    <td><?= e($row['description'] ?? '') ?></td>
    <td><?= e($row['category_name'] ?? '—') ?></td>
    <td><?= e(trim((string) ($row['created_by_name'] ?? '')) !== '' ? $row['created_by_name'] : '—') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
