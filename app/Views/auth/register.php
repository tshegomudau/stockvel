<div class="auth-shell auth-split">
    <section class="auth-hero">
        <p class="brand-kicker">
            <span class="brand-mark" aria-hidden="true">S</span>
            <span>Get started</span>
        </p>
        <h1>Create your stockvel account</h1>
        <p>Choose whether you are creating a new stokvel or joining an existing one. We will guide you to the right next step.</p>
    </section>

    <div class="auth-card card">
        <h1>Create account</h1>
        <p class="lead">All fields marked with * are required.</p>
        <form method="post" action="<?= e(url('/register')) ?>" class="form-stack" novalidate>
            <?= csrf_field() ?>
            <fieldset class="form-group">
                <legend class="form-label required">I want to</legend>
                <label class="form-check">
                    <input type="radio" name="intent" value="create_stokvel" required <?= (string) old('intent', 'create_stokvel') === 'create_stokvel' ? 'checked' : '' ?>>
                    Create a new stokvel (submitted for platform approval)
                </label>
                <label class="form-check">
                    <input type="radio" name="intent" value="join_stokvel" <?= (string) old('intent') === 'join_stokvel' ? 'checked' : '' ?>>
                    Join an existing stokvel (I will receive an invitation)
                </label>
            </fieldset>
            <div class="form-grid two-col">
                <div class="form-group">
                    <label class="form-label required" for="first_name">First name</label>
                    <input class="form-control" type="text" id="first_name" name="first_name" required maxlength="80" value="<?= e((string) old('first_name')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label required" for="last_name">Last name</label>
                    <input class="form-control" type="text" id="last_name" name="last_name" required maxlength="80" value="<?= e((string) old('last_name')) ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label required" for="email">Email</label>
                <input class="form-control" type="email" id="email" name="email" required autocomplete="email" value="<?= e((string) old('email')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="mobile">Mobile</label>
                <input class="form-control" type="tel" id="mobile" name="mobile" inputmode="tel" value="<?= e((string) old('mobile')) ?>" placeholder="+27">
            </div>
            <div class="form-group">
                <label class="form-label required" for="password">Password</label>
                <div class="password-field">
                    <input class="form-control" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                    <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password">Show</button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label required" for="password_confirmation">Confirm password</label>
                <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
            <label class="checkbox">
                <input type="checkbox" name="consent" value="1" required <?= old('consent') ? 'checked' : '' ?>>
                <span>I consent to processing of my personal information for stokvel administration (POPIA).</span>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>
        <div class="auth-links">
            <a href="<?= e(url('/login')) ?>">Already have an account?</a>
            · <a href="<?= e(url('/start-stokvel')) ?>">Submit a stokvel request as a guest</a>
        </div>
    </div>
</div>
