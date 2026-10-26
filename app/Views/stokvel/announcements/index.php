<?= view('partials/page-header', [
    'title' => 'Announcements',
    'description' => 'Draft and publish notices for members.',
    'actionUrl' => url('/app/announcements/create'),
    'actionLabel' => 'New announcement',
], null) ?>
<form method="get" action="<?= e(url('/app/announcements')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach (['draft', 'scheduled', 'published', 'expired'] as $st): ?>
                <option value="<?= e($st) ?>"<?= ($filterStatus ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterStatus ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/announcements')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<?php $list = isset($paginator) ? $paginator->items : []; ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No announcements',
        'emptyActionUrl' => url('/app/announcements/create'),
        'emptyActionLabel' => 'Create announcement',
    ], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Title</th><th>Priority</th><th>Status</th><th>Published</th><th>Author</th><th></th></tr></thead>
<tbody>
<?php foreach ($list as $row): ?>
<tr>
    <td><?= e($row['title'] ?? '') ?></td>
    <td><span class="badge"><?= e($row['priority'] ?? 'normal') ?></span></td>
    <td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
    <td><?= e(sa_datetime($row['publish_at'] ?? '') ?: '—') ?></td>
    <td><?= e(trim((string) ($row['author_name'] ?? '')) !== '' ? $row['author_name'] : '—') ?></td>
    <td class="actions">
        <a class="btn btn-sm btn-secondary" href="<?= e(url('/app/announcements/' . (int) ($row['id'] ?? 0))) ?>">View</a>
        <?php if (in_array((string) ($row['status'] ?? ''), ['draft', 'scheduled'], true)): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('/app/announcements/' . (int) ($row['id'] ?? 0) . '/edit')) ?>">Edit</a>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
