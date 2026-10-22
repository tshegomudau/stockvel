<div class="auth-shell auth-split">
    <section class="auth-hero">
        <p class="brand-kicker">
            <span class="brand-mark" aria-hidden="true">S</span>
            <span>Get started</span>
        </p>
        <h1>Create your stockvel account</h1>
        <p>Register once, then join or create a stokvel. Your personal information is handled with POPIA-oriented controls.</p>
    </section>

    <div class="auth-card card">
        <h1>Create account</h1>
        <p class="lead">All fields marked with * are required.</p>
        <form method="post" action="<?= e(url('/register')) ?>" class="form-stack" novalidate>
            <?= csrf_field() ?>
            <div class="form-grid two-col">
                <div class="form-group">
                    <label class="form-label required" for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" required maxlength="80" value="<?= e((string) old('first_name')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label required" for="last_name">Last name</label>
                    <input type="text" id="last_name" name="last_name" required maxlength="80" value="<?= e((string) old('last_name')) ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label required" for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="email" value="<?= e((string) old('email')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="mobile">Mobile</label>
                <input type="tel" id="mobile" name="mobile" inputmode="tel" value="<?= e((string) old('mobile')) ?>" placeholder="+27">
                <p class="help-text">Optional. Used for invitations and reminders when SMS is configured.</p>
            </div>
            <div class="form-group">
                <label class="form-label required" for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                    <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password">Show</button>
                </div>
                <p class="help-text">At least 8 characters. Prefer a unique passphrase.</p>
            </div>
            <div class="form-group">
                <label class="form-label required" for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
            <label class="checkbox">
                <input type="checkbox" name="consent" value="1" required <?= old('consent') ? 'checked' : '' ?>>
                <span>I consent to processing of my personal information for stokvel administration (POPIA).</span>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>
        <div class="auth-links">
            <a href="<?= e(url('/login')) ?>">Already have an account?</a>
        </div>
    </div>
</div>
