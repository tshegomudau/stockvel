<div class="auth-card card">
    <h1>You're invited</h1>
    <p>You have been invited to join <strong><?= e($invitation['stokvel_name'] ?? 'a stokvel') ?></strong> as <?= e($invitation['role'] ?? 'member') ?>.</p>
    <div class="grid grid-2">
        <form method="post" action="<?= e(url('/invitations/' . urlencode($token) . '/accept')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-block">Accept invitation</button>
        </form>
        <form method="post" action="<?= e(url('/invitations/' . urlencode($token) . '/reject')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-block" data-confirm="Decline this invitation?">Decline</button>
        </form>
    </div>
</div>
