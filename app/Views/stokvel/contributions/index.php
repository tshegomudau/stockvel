<div class="page-header">
    <h1>Contributions</h1>
    <div class="toolbar">
        <a class="btn btn-primary" href="<?= e(url('/app/contributions/create')) ?>">Capture</a>
        <a class="btn" href="<?= e(url('/app/contributions/bulk')) ?>">Bulk</a>
        <a class="btn" href="<?= e(url('/app/contributions/import')) ?>">Import CSV</a>
        <a class="btn" href="<?= e(url('/app/contributions/arrears')) ?>">Arrears</a>
    </div>
</div>
<table class="table">
    <thead><tr><th>Date</th><th>Member</th><th>Amount</th><th>Method</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e(sa_date($row['paid_at'] ?? '')) ?></td>
            <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
            <td><?= e(money($row['amount'] ?? 0)) ?></td>
            <td><?= e($row['payment_method'] ?? '') ?></td>
            <td><?= e($row['status'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
