<div class="auth-card card">
    <h1>You're invited</h1>
    <p>You have been invited to join <strong><?= e($invitation['stokvel_name'] ?? 'a stokvel') ?></strong> as <?= e($invitation['role'] ?? 'member') ?>.</p>
    <?php if (!empty($invitation['email'])): ?>
        <p class="text-muted">Invitation sent to <strong><?= e((string) $invitation['email']) ?></strong>.</p>
    <?php endif; ?>

    <?php if (empty($isLoggedIn)): ?>
        <p>Sign in or create an account to accept. After you accept, an admin must approve your membership.</p>
        <div class="form-actions" style="display:flex;gap:.75rem;flex-wrap:wrap;">
            <a class="btn btn-primary" href="<?= e(url('/login')) ?>">Sign in to accept</a>
            <a class="btn btn-secondary" href="<?= e(url('/register')) ?>">Create account</a>
        </div>
        <form method="post" action="<?= e(url('/invitations/' . rawurlencode((string) $token) . '/reject')) ?>" class="mt-4">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost" data-confirm="Decline this invitation?">Decline invitation</button>
        </form>
    <?php else: ?>
        <?php
        $userEmail = strtolower(trim((string) ($currentUser['email'] ?? '')));
        $inviteEmail = strtolower(trim((string) ($invitation['email'] ?? '')));
        ?>
        <?php if ($inviteEmail !== '' && $userEmail !== '' && $userEmail !== $inviteEmail): ?>
            <p class="callout callout-warning">
                You are signed in as <strong><?= e($userEmail) ?></strong>, but this invitation was sent to
                <strong><?= e($inviteEmail) ?></strong>. You can still accept if that is intentional.
            </p>
        <?php endif; ?>
        <div class="grid grid-2">
            <form method="post" action="<?= e(url('/invitations/' . rawurlencode((string) $token) . '/accept')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-block">Accept invitation</button>
            </form>
            <form method="post" action="<?= e(url('/invitations/' . rawurlencode((string) $token) . '/reject')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-block" data-confirm="Decline this invitation?">Decline</button>
            </form>
        </div>
    <?php endif; ?>
</div>
