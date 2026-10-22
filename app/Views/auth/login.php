<div class="auth-shell auth-split">
    <section class="auth-hero">
        <p class="brand-kicker">
            <span class="brand-mark" aria-hidden="true">S</span>
            <span>stockvel</span>
        </p>
        <h1>Manage your stokvel with clarity and trust</h1>
        <p>Secure multi-tenant tooling for contributions, expenses, meetings, payouts, and member statements — built for South African groups.</p>
        <ul class="auth-points">
            <li>Tenant-isolated records and role-based permissions</li>
            <li>ZAR-friendly financial workflows with audit history</li>
            <li>Member self-service for statements and documents</li>
        </ul>
    </section>

    <div class="auth-card card">
        <h1>Sign in</h1>
        <p class="lead">Use your email and password to access your stokvel workspace.</p>
        <form method="post" action="<?= e(url('/login')) ?>" class="form-stack" novalidate>
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label required" for="email">Email</label>
                <input type="email" id="email" name="email" required autocomplete="username" value="<?= e((string) old('email')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label required" for="password">Password</label>
                <div class="password-field">
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <button type="button" class="password-toggle" data-password-toggle="password" aria-label="Show password">Show</button>
                </div>
            </div>
            <label class="checkbox">
                <input type="checkbox" name="remember" value="1">
                <span>Keep me signed in on this device</span>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>
        <div class="auth-links">
            <a href="<?= e(url('/forgot-password')) ?>">Forgot password?</a>
            <a href="<?= e(url('/register')) ?>">Create account</a>
        </div>
        <p class="auth-security-note">Protected with CSRF tokens, rate limiting, and secure session cookies. Never share your password.</p>
    </div>
</div>
