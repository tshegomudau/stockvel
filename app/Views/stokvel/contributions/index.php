<?= view('partials/page-header', [
    'title' => 'Contributions',
    'description' => 'Capture payments, review history, and manage arrears.',
], null) ?>
<div class="page-actions" style="margin:-0.5rem 0 1rem;">
    <a class="btn btn-primary" href="<?= e(url('/app/contributions/create')) ?>">Record payment</a>
    <a class="btn btn-secondary" href="<?= e(url('/app/contributions/bulk')) ?>">Bulk capture</a>
    <a class="btn btn-secondary" href="<?= e(url('/app/contributions/import')) ?>">CSV import</a>
    <a class="btn btn-secondary" href="<?= e(url('/app/contributions/arrears')) ?>">Arrears</a>
</div>
<?php $list = isset($paginator) ? $paginator->items : ($rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No contributions yet', 'emptyMessage' => 'Record a payment to see it listed here.', 'emptyActionUrl' => url('/app/contributions/create'), 'emptyActionLabel' => 'Record payment'], null) ?>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Member</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($list as $row): ?>
        <tr>
            <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
            <td class="money"><?= e(money($row['amount'] ?? 0)) ?></td>
            <td><?= e($row['payment_method'] ?? '') ?></td>
            <td><?= e(sa_date($row['paid_at'] ?? '')) ?></td>
            <td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
