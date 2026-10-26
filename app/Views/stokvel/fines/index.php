<?= view('partials/page-header', [
    'title' => 'Fines',
    'description' => 'Issue and track member fines.',
    'actionUrl' => url('/app/fines/create'),
    'actionLabel' => 'Issue fine',
], null) ?>
<p class="text-muted">
    <a href="<?= e(url('/app/fine-types')) ?>">Fine types</a>
    · <a href="<?= e(url('/app/fines/bulk')) ?>">Bulk issue</a>
    · <a href="<?= e(url('/app/fines/auto')) ?>">Automatic fines</a>
</p>
<form method="get" action="<?= e(url('/app/fines')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Member, type, or reason">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach ($statusOptions ?? [] as $st): ?>
                <option value="<?= e($st) ?>"<?= ($filterStatus ?? '') === $st ? ' selected' : '' ?>><?= e(str_replace('_', ' ', ucfirst($st))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="fine_type_id">Fine type</label>
        <select id="fine_type_id" name="fine_type_id">
            <option value="">All types</option>
            <?php foreach ($filterTypes ?? [] as $type): ?>
                <option value="<?= (int) $type['id'] ?>"<?= (int) ($filterTypeId ?? 0) === (int) $type['id'] ? ' selected' : '' ?>><?= e($type['name'] ?? '') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterQ ?? '') !== '' || ($filterStatus ?? '') !== '' || (int) ($filterTypeId ?? 0) > 0): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/fines')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<?php $list = isset($paginator) ? $paginator->items : []; ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No fines match',
        'emptyActionUrl' => url('/app/fines/create'),
        'emptyActionLabel' => 'Issue fine',
    ], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead>
<tr>
    <th>Member</th>
    <th>Type</th>
    <th>Amount</th>
    <th>Paid</th>
    <th>Status</th>
    <th>Issued</th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
    <td>
        <?= e(trim((string) ($row['member_name'] ?? '')) !== '' ? $row['member_name'] : ($row['member_email'] ?? '—')) ?>
    </td>
    <td><?= e($row['fine_type_name_snapshot'] ?? '') ?></td>
    <td class="money"><?= e(money($row['amount'] ?? 0)) ?></td>
    <td class="money"><?= e(money($row['amount_paid'] ?? 0)) ?></td>
    <td><span class="badge"><?= e(str_replace('_', ' ', (string) ($row['status'] ?? ''))) ?></span></td>
    <td><?= e(sa_date($row['issued_at'] ?? '')) ?></td>
    <td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/fines/' . (int) ($row['id'] ?? 0))) ?>">View</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
