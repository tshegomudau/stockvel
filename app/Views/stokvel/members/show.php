<div class="page-header">
    <h1><?= e(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?></h1>
    <a class="btn" href="<?= e(url('/app/members/' . ($member['id'] ?? '') . '/statement')) ?>">Statement</a>
</div>
<div class="card">
    <dl class="detail-list">
        <dt>Email</dt><dd><?= e($member['email'] ?? '') ?></dd>
        <dt>Mobile</dt><dd><?= e($member['mobile'] ?? '—') ?></dd>
        <dt>Role</dt><dd><?= e($member['role'] ?? '') ?></dd>
        <dt>Status</dt><dd><?= e($member['status'] ?? '') ?></dd>
        <dt>Joined</dt><dd><?= e(sa_date($member['joined_at'] ?? $member['created_at'] ?? '')) ?></dd>
    </dl>
    <div class="toolbar">
        <?php if (($member['status'] ?? '') === 'active'): ?>
            <form method="post" action="<?= e(url('/app/members/' . ($member['id'] ?? '') . '/suspend')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger" data-confirm="Suspend this member?">Suspend</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('/app/members/' . ($member['id'] ?? '') . '/reinstate')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Reinstate</button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/app/members/' . ($member['id'] ?? '') . '/resign')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn" data-confirm="Mark member as resigned?">Mark resigned</button>
        </form>
    </div>
</div>
