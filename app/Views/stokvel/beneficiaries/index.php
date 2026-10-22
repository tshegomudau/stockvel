<?= view('partials/page-header', [
    'title' => 'Beneficiaries',
    'description' => 'All member beneficiaries across the stokvel. Member profile changes still use the approval workflow.',
    'actionUrl' => !empty($canManage) ? url('/app/beneficiaries/create') : null,
    'actionLabel' => !empty($canManage) ? 'Add beneficiary' : null,
], null) ?>

<form method="get" action="<?= e(url('/app/beneficiaries')) ?>" class="form-stack card mb-6">
    <div class="form-grid two-col">
        <div class="form-group">
            <label for="q">Search</label>
            <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Name or relationship">
        </div>
        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All</option>
                <option value="active"<?= ($filterStatus ?? '') === 'active' ? ' selected' : '' ?>>Active</option>
                <option value="inactive"<?= ($filterStatus ?? '') === 'inactive' ? ' selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="form-group">
            <label for="member_id">Member</label>
            <select id="member_id" name="member_id">
                <option value="">All members</option>
                <?php foreach ($members ?? [] as $m): ?>
                    <option value="<?= (int) $m['id'] ?>"<?= (int) ($filterMemberId ?? 0) === (int) $m['id'] ? ' selected' : '' ?>>
                        <?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?> (<?= e($m['member_number'] ?? '') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="toolbar">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterQ ?? '') !== '' || ($filterStatus ?? '') !== '' || (int) ($filterMemberId ?? 0) > 0): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/beneficiaries')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php $list = isset($paginator) ? $paginator->items : []; ?>
<?php if ($list === []): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No beneficiaries match',
        'emptyMessage' => !empty($canManage)
            ? 'Try clearing filters, or add a beneficiary.'
            : 'Try clearing filters or ask an admin to add beneficiaries.',
    ], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Beneficiary</th><th>Member</th><th>Relationship</th><th>Share</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<?php
    $bid = (int) ($row['id'] ?? 0);
    $rowStatus = (string) ($row['status'] ?? 'active');
    $statusClass = $rowStatus === 'active' ? 'badge-success' : 'badge-warning';
?>
<tr>
    <td>
        <a href="<?= e(url('/app/beneficiaries/' . $bid)) ?>"><?= e($row['full_name'] ?? '') ?></a>
        <?= !empty($row['is_primary']) ? ' <span class="badge badge-primary">Primary</span>' : '' ?>
    </td>
    <td><a href="<?= e(url('/app/members/' . (int) ($row['membership_id'] ?? 0))) ?>"><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></a></td>
    <td><?= e($row['relationship'] ?? '') ?></td>
    <td><?= e(number_format((float) ($row['share_percent'] ?? 0), 2)) ?>%</td>
    <td><span class="badge <?= e($statusClass) ?>"><?= e(ucfirst($rowStatus)) ?></span></td>
    <td class="actions">
        <a class="btn btn-sm btn-ghost" href="<?= e(url('/app/beneficiaries/' . $bid)) ?>">View</a>
        <?php if (!empty($canManage)): ?>
            <a class="btn btn-sm btn-secondary" href="<?= e(url('/app/beneficiaries/' . $bid . '/edit')) ?>">Edit</a>
            <?php if ($rowStatus === 'active'): ?>
            <form method="post" action="<?= e(url('/app/beneficiaries/' . $bid . '/deactivate')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-ghost">Deactivate</button></form>
            <?php else: ?>
            <form method="post" action="<?= e(url('/app/beneficiaries/' . $bid . '/reactivate')) ?>" class="inline-form"><?= csrf_field() ?><button type="submit" class="btn btn-sm btn-primary">Reactivate</button></form>
            <?php endif; ?>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
