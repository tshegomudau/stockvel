<div class="page-header">
    <h1>Loans</h1>
    <a class="btn btn-primary" href="<?= e(url('/app/loans/create')) ?>">Add Loan</a>
</div>
<?php if (empty($paginator->items)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No records', 'emptyActionUrl' => url('/app/loans/create'), 'emptyActionLabel' => 'Add Loan'], null) ?>
<?php else: ?>
<table class="table">
    <thead><tr><th>ID</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e((string) ($row['id'] ?? '')) ?></td>
            <td><?= e(sa_date($row['created_at'] ?? '')) ?></td>
            <td><a href="<?= e(url('/app/loans/' . ($row['id'] ?? ''))) ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
