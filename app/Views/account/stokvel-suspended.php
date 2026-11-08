<?php $stokvels = $stokvels ?? []; ?>
<div class="auth-shell">
    <div class="auth-card card" style="max-width:36rem">
        <h1>Stokvel suspended</h1>
        <p class="lead">Access is paused because your stokvel has been suspended by the platform administrator.</p>
        <?php if ($stokvels !== []): ?>
            <ul class="form-stack" style="list-style:none;padding:0;margin:0 0 1rem;gap:.5rem">
                <?php foreach ($stokvels as $row): ?>
                    <li class="card" style="padding:.85rem 1rem;box-shadow:none">
                        <?= e((string) ($row['name'] ?? 'Stokvel')) ?>
                        — <span class="badge">Suspended</span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="form-help">You can sign out now. Contact your platform administrator if you believe this is a mistake.</p>
        <div class="auth-links mt-4">
            <a class="btn btn-primary" href="<?= e(url('/logout')) ?>">Sign out</a>
        </div>
    </div>
</div>
