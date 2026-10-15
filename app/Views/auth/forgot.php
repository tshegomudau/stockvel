<div class="auth-card card">
    <h1>Forgot password</h1>
    <p>Enter your email and we will send reset instructions if an account exists.</p>
    <form method="post" action="<?= e(url('/forgot-password')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
    </form>
</div>
