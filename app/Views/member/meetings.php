<table class="table">
    <thead><tr><th>Meeting</th><th>When</th><th>Minutes ack</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($meetings as $m): ?>
        <tr>
            <td><?= e($m['title'] ?? '') ?></td>
            <td><?= e(sa_datetime($m['scheduled_at'] ?? '')) ?></td>
            <td><?= !empty($m['acknowledged_at']) ? e(sa_date($m['acknowledged_at'])) : 'Pending' ?></td>
            <td>
                <?php if (empty($m['acknowledged_at'])): ?>
                    <form method="post" action="<?= e(url('/member/meetings/' . ($m['id'] ?? '') . '/acknowledge')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-primary">Acknowledge minutes</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
