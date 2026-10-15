<form method="get" class="toolbar">
    <input type="search" name="q" placeholder="Search stokvels" value="<?= e($search ?? '') ?>">
    <button type="submit" class="btn">Search</button>
</form>
<?php if (empty($paginator->items)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No stokvels found'], null) ?>
<?php else: ?>
<table class="table">
    <thead><tr><th>Name</th><th>Status</th><th>Registered</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e($row['name'] ?? '') ?></td>
            <td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
            <td><?= e(sa_date($row['created_at'] ?? '')) ?></td>
            <td><a href="<?= e(url('/platform/tenants/' . ($row['id'] ?? ''))) ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
