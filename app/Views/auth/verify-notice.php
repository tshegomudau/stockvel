<div class="auth-card card">
    <h1>Verify your email</h1>
    <p>We sent a verification link to your email address. Please verify before accessing all features.</p>
    <form method="post" action="<?= e(url('/verify-email/resend')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">Resend verification email</button>
    </form>
    <p class="auth-links"><a href="<?= e(url('/app')) ?>">Continue to dashboard</a></p>
</div>
