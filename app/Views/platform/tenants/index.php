<?= view('partials/page-header', [
    'title' => 'Stokvels',
    'description' => 'Platform-managed tenants.',
    'actionUrl' => !empty($canCreate) ? url('/platform/tenants/create') : null,
    'actionLabel' => 'Create stokvel',
], null) ?>
<form method="get" class="toolbar filter-bar" role="search">
    <div class="form-group">
        <label class="sr-only" for="tenant-search">Search</label>
        <input class="form-control" type="search" id="tenant-search" name="q" placeholder="Search stokvels" value="<?= e($search ?? '') ?>">
    </div>
    <div class="form-group">
        <label class="sr-only" for="tenant-status">Status</label>
        <select class="form-control form-select" id="tenant-status" name="status">
            <option value="">All statuses</option>
            <?php foreach (['active', 'suspended', 'draft', 'pending'] as $st): ?>
                <option value="<?= e($st) ?>"<?= ($statusFilter ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($search ?? '') !== '' || ($statusFilter ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/platform/tenants')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<?php if (empty($paginator->items)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No stokvels found'], null) ?>
<?php else: ?>
<div class="table-wrap">
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
</div>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
