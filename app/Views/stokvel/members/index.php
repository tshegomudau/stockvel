<div class="page-header">
    <h1>Members</h1>
    <a class="btn btn-primary" href="<?= e(url('/app/members/invite')) ?>">Invite member</a>
</div>
<form method="get" class="toolbar">
    <select name="status">
        <option value="">All statuses</option>
        <?php foreach (['active', 'suspended', 'resigned'] as $st): ?>
            <option value="<?= e($st) ?>"<?= ($statusFilter ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn">Filter</button>
</form>
<table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
            <td><?= e($row['email'] ?? '') ?></td>
            <td><?= e($row['role'] ?? '') ?></td>
            <td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
            <td><a href="<?= e(url('/app/members/' . ($row['id'] ?? ''))) ?>">View</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
