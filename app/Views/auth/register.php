<div class="auth-card card">
    <h1>Create account</h1>
    <form method="post" action="<?= e(url('/register')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="grid grid-2">
            <div class="form-group">
                <label for="first_name">First name</label>
                <input type="text" id="first_name" name="first_name" required value="<?= e((string) old('first_name')) ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Last name</label>
                <input type="text" id="last_name" name="last_name" required value="<?= e((string) old('last_name')) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= e((string) old('email')) ?>">
        </div>
        <div class="form-group">
            <label for="mobile">Mobile (optional)</label>
            <input type="tel" id="mobile" name="mobile" value="<?= e((string) old('mobile')) ?>" placeholder="+27">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label for="password_confirmation">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
        </div>
        <label class="checkbox">
            <input type="checkbox" name="consent" value="1" required>
            I consent to processing of my personal information (POPIA).
        </label>
        <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>
    <p class="auth-links"><a href="<?= e(url('/login')) ?>">Already have an account?</a></p>
</div>
