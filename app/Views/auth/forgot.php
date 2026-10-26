<div class="auth-shell" style="max-width:28rem;margin:0 auto;">
    <div class="auth-card card">
        <h1>Forgot password</h1>
        <p class="lead">Enter your account email. If it exists, we will send a one-time reset link.</p>
        <form method="post" action="<?= e(url('/forgot-password')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label required" for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="<?= e((string) old('email')) ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
        </form>
        <div class="auth-links">
            <a href="<?= e(url('/login')) ?>">Back to sign in</a>
        </div>
    </div>
</div>
