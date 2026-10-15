<form method="get" class="toolbar">
    <input type="search" name="q" placeholder="Search users" value="<?= e($search ?? '') ?>">
    <button type="submit" class="btn">Search</button>
</form>
<table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Platform role</th><th>Verified</th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
            <td><?= e($row['email'] ?? '') ?></td>
            <td><?= e($row['platform_role'] ?? '—') ?></td>
            <td><?= !empty($row['email_verified_at']) ? 'Yes' : 'No' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
