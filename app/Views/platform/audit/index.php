<?= view('partials/page-header', [
    'title' => 'Audit log',
    'description' => 'Platform actions with clear usernames (not raw IDs).',
], null) ?>
<form method="get" class="toolbar grid grid-3">
    <input type="text" name="action" placeholder="Action" value="<?= e($filters['action'] ?? '') ?>">
    <input type="number" name="user_id" placeholder="User ID" value="<?= e((string) ($filters['user_id'] ?? '')) ?>">
    <button type="submit" class="btn">Filter</button>
</form>
<table class="table">
    <thead><tr><th>When</th><th>User</th><th>Stokvel</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <?php
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            $userLabel = $name !== '' ? $name : (string) ($row['email'] ?? ('User #' . ($row['user_id'] ?? '')));
        ?>
        <tr>
            <td><?= e(sa_datetime($row['created_at'] ?? '')) ?></td>
            <td>
                <strong><?= e($userLabel) ?></strong>
                <?php if (!empty($row['email']) && $name !== ''): ?>
                    <div class="text-sm text-muted"><?= e($row['email']) ?></div>
                <?php endif; ?>
            </td>
            <td><?= e($row['stokvel_name'] ?? '—') ?></td>
            <td><?= e($row['action'] ?? '') ?></td>
            <td><?= e(($row['entity_type'] ?? '') . ' #' . ($row['entity_id'] ?? '')) ?></td>
            <td><?= e($row['ip_address'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
