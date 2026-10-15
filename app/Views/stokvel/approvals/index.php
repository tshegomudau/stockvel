<form method="get" class="toolbar">
    <select name="status">
        <?php foreach (['pending', 'approved', 'rejected'] as $st): ?>
            <option value="<?= e($st) ?>"<?= ($statusFilter ?? 'pending') === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn">Filter</button>
</form>
<table class="table">
    <thead><tr><th>Type</th><th>Reference</th><th>Status</th><th>Requested</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e($row['entity_type'] ?? $row['type'] ?? '') ?></td>
            <td>#<?= e((string) ($row['entity_id'] ?? $row['id'] ?? '')) ?></td>
            <td><?= e($row['status'] ?? '') ?></td>
            <td><?= e(sa_datetime($row['created_at'] ?? '')) ?></td>
            <td>
                <?php if (($row['status'] ?? '') === 'pending'): ?>
                    <form method="post" action="<?= e(url('/app/approvals/' . ($row['id'] ?? '') . '/approve')) ?>" style="display:inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                    </form>
                    <form method="post" action="<?= e(url('/app/approvals/' . ($row['id'] ?? '') . '/reject')) ?>" style="display:inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm" data-confirm="Reject this request?">Reject</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
