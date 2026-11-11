<?= view('partials/page-header', [
    'title' => 'Stokvel creation requests',
    'description' => 'Guest and public requests awaiting platform review.',
], null) ?>
<form method="get" class="filter-bar">
    <div class="form-group">
        <label for="status">Status</label>
        <select class="form-control form-select" id="status" name="status" onchange="this.form.submit()">
            <?php foreach (['pending','approved','rejected','expired','claimed',''] as $st): ?>
                <option value="<?= e($st) ?>"<?= ($filterStatus ?? '') === $st ? ' selected' : '' ?>><?= e($st === '' ? 'All' : ucfirst($st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Stokvel</th><th>Requester</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e($row['stokvel_name'] ?? '') ?></td>
            <td><?= e(trim(($row['requester_first_name'] ?? '') . ' ' . ($row['requester_last_name'] ?? ''))) ?><div class="text-muted text-sm"><?= e($row['requester_email'] ?? '') ?></div></td>
            <td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
            <td><?= e(sa_datetime($row['created_at'] ?? '')) ?></td>
            <td><a class="btn btn-sm btn-secondary" href="<?= e(url('/platform/stokvel-requests/' . (int) $row['id'])) ?>">Review</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
