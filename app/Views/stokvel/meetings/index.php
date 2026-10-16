<?= view('partials/page-header', [
    'title' => 'Meetings',
    'description' => 'Schedule meetings, capture attendance, minutes, and resolutions.',
    'actionUrl' => url('/app/meetings/create'),
    'actionLabel' => 'Schedule meeting',
], null) ?>
<?php $list = isset($paginator) ? $paginator->items : ($items ?? $rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No meetings scheduled', 'emptyActionUrl' => url('/app/meetings/create'), 'emptyActionLabel' => 'Schedule meeting'], null) ?>
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
