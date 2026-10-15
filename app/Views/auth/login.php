<div class="auth-card card">
    <h1>Sign in</h1>
    <form method="post" action="<?= e(url('/login')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required autocomplete="email" value="<?= e((string) old('email')) ?>">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <label class="checkbox">
            <input type="checkbox" name="remember" value="1"> Remember me
        </label>
        <button type="submit" class="btn btn-primary btn-block">Sign in</button>
    </form>
    <p class="auth-links">
        <a href="<?= e(url('/forgot-password')) ?>">Forgot password?</a>
        ·
        <a href="<?= e(url('/register')) ?>">Create account</a>
    </p>
</div>
