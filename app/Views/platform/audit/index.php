<form method="get" class="toolbar grid grid-3">
    <input type="text" name="action" placeholder="Action" value="<?= e($filters['action'] ?? '') ?>">
    <input type="number" name="user_id" placeholder="User ID" value="<?= e((string) ($filters['user_id'] ?? '')) ?>">
    <button type="submit" class="btn">Filter</button>
</form>
<table class="table">
    <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e(sa_datetime($row['created_at'] ?? '')) ?></td>
            <td><?= e((string) ($row['user_id'] ?? '')) ?></td>
            <td><?= e($row['action'] ?? '') ?></td>
            <td><?= e(($row['entity_type'] ?? '') . ' #' . ($row['entity_id'] ?? '')) ?></td>
            <td><?= e($row['ip_address'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
