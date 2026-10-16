<div class="auth-shell" style="max-width:32rem;margin:0 auto;">
    <div class="auth-card card">
        <h1>Verify your email</h1>
        <p class="lead">We sent a verification link to your inbox. In local development, check <code>storage/mail</code>.</p>
        <form method="post" action="<?= e(url('/verify-email/resend')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-block">Resend verification email</button>
        </form>
        <div class="auth-links">
            <a href="<?= e(url('/app')) ?>">Continue to app</a>
        </div>
    </div>
</div>
