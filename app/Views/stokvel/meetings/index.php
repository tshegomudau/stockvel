<?= view('partials/page-header', [
    'title' => 'Meetings',
    'description' => 'Schedule meetings, capture attendance, minutes, and resolutions.',
    'actionUrl' => url('/app/meetings/create'),
    'actionLabel' => 'Schedule meeting',
], null) ?>
<form method="get" action="<?= e(url('/app/meetings')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Title, venue, or ref">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach ($statusOptions ?? ['scheduled', 'completed', 'cancelled'] as $st): ?>
                <option value="<?= e($st) ?>"<?= ($filterStatus ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterQ ?? '') !== '' || ($filterStatus ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/meetings')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<?php $list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No meetings match', 'emptyActionUrl' => url('/app/meetings/create'), 'emptyActionLabel' => 'Schedule meeting'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Title</th><th>When</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
<td><?= e($row['title'] ?? '') ?></td>
<td><?= e(sa_datetime($row['scheduled_at'] ?? '')) ?></td>
<td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
<td class="actions"><a class="btn btn-sm btn-secondary" href="<?= e(url('/app/meetings/' . ($row['id'] ?? ''))) ?>">Open</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
