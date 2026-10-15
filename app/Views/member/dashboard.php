<div class="grid grid-3">
    <div class="stat-card card">
        <span class="stat-label">My balance</span>
        <strong class="stat-value"><?= e(money($balance ?? 0)) ?></strong>
    </div>
    <div class="stat-card card">
        <span class="stat-label">Role</span>
        <strong class="stat-value"><?= e($membership['role'] ?? 'Member') ?></strong>
    </div>
    <div class="stat-card card">
        <span class="stat-label">Status</span>
        <strong class="stat-value"><?= e($membership['status'] ?? '') ?></strong>
    </div>
</div>
<div class="card" style="margin-top:1.5rem;">
    <h2>Upcoming meetings</h2>
    <?php if (empty($upcomingMeetings)): ?>
        <p>No upcoming meetings scheduled.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($upcomingMeetings as $m): ?>
                <li><?= e($m['title'] ?? 'Meeting') ?> — <?= e(sa_datetime($m['scheduled_at'] ?? '')) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <a class="btn btn-sm" href="<?= e(url('/member/meetings')) ?>">All meetings</a>
</div>
