<?= view('partials/page-header', [
    'title' => 'Platform admins',
    'description' => 'Create and manage platform administrator accounts and permissions.',
    'actionUrl' => !empty($canManage) ? url('/platform/admins/create') : null,
    'actionLabel' => 'Add admin',
], null) ?>
<form method="get" class="filter-bar"><input class="form-control" type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Search email or name"><button class="btn btn-secondary" type="submit">Search</button></form>
<table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
            <td><?= e($row['email'] ?? '') ?></td>
            <td><?= e($row['platform_role'] ?? '') ?></td>
            <td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
            <td><a class="btn btn-sm btn-secondary" href="<?= e(url('/platform/admins/' . (int) $row['id'] . '/edit')) ?>">Edit</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
