<?= view('partials/page-header', [
    'title' => 'Meetings',
    'description' => 'View agendas, notes, minutes, and linked documents. Acknowledge minutes when ready.',
], null) ?>

<?php if (($meetings ?? []) === []): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No meetings yet',
        'emptyMessage' => 'When your stokvel schedules meetings, they will appear here.',
    ], null) ?>
<?php else: ?>
<table class="table">
    <thead><tr><th>Meeting</th><th>When</th><th>Minutes</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($meetings as $m): ?>
        <tr>
            <td>
                <a href="<?= e(url('/member/meetings/' . (int) ($m['id'] ?? 0))) ?>">
                    <?= e($m['title'] ?? 'Meeting') ?>
                </a>
            </td>
            <td><?= e(sa_datetime($m['scheduled_at'] ?? '')) ?></td>
            <td><?= !empty($m['acknowledged_at']) ? e(sa_date($m['acknowledged_at'])) : 'Pending' ?></td>
            <td>
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/member/meetings/' . (int) ($m['id'] ?? 0))) ?>">View</a>
                <?php if (empty($m['acknowledged_at']) && !empty($m['minutes_html'])): ?>
                    <form method="post" action="<?= e(url('/member/meetings/' . (int) ($m['id'] ?? 0) . '/acknowledge')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-primary">Acknowledge</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
