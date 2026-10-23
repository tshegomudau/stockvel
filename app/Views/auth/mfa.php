<div class="auth-card">
    <h1>Verify it’s you</h1>
    <p class="text-muted">Enter the 6-digit code from your authenticator app, or use a recovery code<?= !empty($hasPasskeys) ? ' / passkey' : '' ?>.</p>

    <?php if (!empty($totpEnabled)): ?>
    <form method="post" action="<?= e(url('/login/mfa')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="code">Authentication code</label>
            <input id="code" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" pattern="[0-9A-Za-z\-]{6,16}" placeholder="123456">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Continue</button>
    </form>
    <?php endif; ?>

    <?php if (!empty($hasPasskeys)): ?>
    <div class="mt-6 form-stack" data-mfa-passkey
         data-options-url="<?= e(url('/login/mfa/passkey/options')) ?>"
         data-verify-url="<?= e(url('/login/mfa/passkey')) ?>"
         data-redirect-url="<?= e(url('/dashboard')) ?>">
        <button type="button" class="btn btn-secondary btn-block" id="mfa-passkey-btn">Use passkey</button>
        <p id="mfa-passkey-error" class="form-error" hidden></p>
    </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/login/mfa/cancel')) ?>" class="mt-6">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost btn-block">Cancel sign-in</button>
    </form>
</div>
