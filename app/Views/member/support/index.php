<?= view('partials/page-header', [
    'title' => 'Support & bugs',
    'description' => 'Log issues and track replies. Always available in the member portal.',
    'actionUrl' => url('/member/support/create'),
    'actionLabel' => 'Log a ticket',
], null) ?>
<?php if (empty($paginator->items)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No tickets yet',
        'emptyMessage' => 'Raise a bug or support request when something needs attention.',
        'emptyActionUrl' => url('/member/support/create'),
        'emptyActionLabel' => 'Log a ticket',
    ], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>#</th><th>Subject</th><th>Category</th><th>Status</th><th>Updated</th></tr></thead>
<tbody>
<?php foreach ($paginator->items as $row): ?>
<tr>
<td><a href="<?= e(url('/member/support/' . $row['id'])) ?>">#<?= (int) $row['id'] ?></a></td>
<td><?= e($row['subject'] ?? '') ?></td>
<td><?= e($row['category'] ?? '') ?></td>
<td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
<td><?= e(sa_datetime($row['updated_at'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
