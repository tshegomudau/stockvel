<div class="page-header">
    <h1>Rules</h1>
    <a class="btn btn-primary" href="<?= e(url('/app/rules/create')) ?>">Add Rule</a>
</div>
<?php if (empty($paginator->items)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No records', 'emptyActionUrl' => url('/app/rules/create'), 'emptyActionLabel' => 'Add Rule'], null) ?>
<?php else: ?>
<table class="table">
    <thead><tr><th>ID</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e((string) ($row['id'] ?? '')) ?></td>
            <td><?= e(sa_date($row['created_at'] ?? '')) ?></td>
            <td><a href="<?= e(url('/app/rules/' . ($row['id'] ?? ''))) ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
