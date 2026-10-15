<div class="page-header">
    <h1>Payouts</h1>
    <a class="btn btn-primary" href="<?= e(url('/app/payouts/create')) ?>">Add Payout</a>
</div>
<?php if (empty($paginator->items)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No records', 'emptyActionUrl' => url('/app/payouts/create'), 'emptyActionLabel' => 'Add Payout'], null) ?>
<?php else: ?>
<table class="table">
    <thead><tr><th>ID</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e((string) ($row['id'] ?? '')) ?></td>
            <td><?= e(sa_date($row['created_at'] ?? '')) ?></td>
            <td><a href="<?= e(url('/app/payouts/' . ($row['id'] ?? ''))) ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
