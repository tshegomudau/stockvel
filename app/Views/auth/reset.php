<div class="auth-shell" style="max-width:28rem;margin:0 auto;">
    <div class="auth-card card">
        <h1>Reset password</h1>
        <p class="lead">Choose a new password for your account.</p>
        <form method="post" action="<?= e(url('/reset-password/' . ($token ?? ''))) ?>" class="form-stack">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label required" for="password">New password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                    <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password">Show</button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label required" for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Update password</button>
        </form>
    </div>
</div>
