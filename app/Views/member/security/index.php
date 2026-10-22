<?php
$securityBase = $securityBase ?? '/member/security';
$profileBackUrl = $profileBackUrl ?? '/member/profile';
?>
<?= view('partials/page-header', [
    'title' => 'Security & MFA',
    'description' => 'Optional authenticator app (TOTP) and passkeys. Login will require a second factor once enabled.',
], null) ?>

<p class="mb-4"><a href="<?= e(url($profileBackUrl)) ?>">← Back to profile</a></p>

<?php if (!empty($recoveryCodes)): ?>
<section class="card mb-6">
    <h2>Save your recovery codes</h2>
    <p class="text-muted">Each code works once. Store them offline — they will not be shown again.</p>
    <ul class="form-stack">
        <?php foreach ($recoveryCodes as $code): ?>
            <li><code><?= e((string) $code) ?></code></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="card mb-6 form-stack">
    <h2>Authenticator app (TOTP)</h2>
    <?php if (!empty($totpEnabled) && empty($pendingSecret)): ?>
        <p class="text-success">Enabled. Sign-in will ask for a 6-digit code (or a recovery code).</p>
        <form method="post" action="<?= e(url($securityBase . '/totp/disable')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="disable_code">Current code or recovery code</label>
                <input id="disable_code" name="code" required autocomplete="one-time-code" inputmode="numeric" pattern="[0-9A-Za-z\-]{6,16}">
            </div>
            <button type="submit" class="btn btn-secondary">Disable authenticator</button>
        </form>
    <?php elseif (!empty($pendingSecret)): ?>
        <p>Add this account in Google Authenticator, Authy, or similar.</p>
        <?php if (!empty($qrSvg)): ?>
            <div class="mb-4" aria-hidden="true"><?= $qrSvg ?></div>
        <?php endif; ?>
        <div class="form-group">
            <label class="form-label">Manual secret</label>
            <code class="d-block" style="word-break:break-all;"><?= e((string) $pendingSecret) ?></code>
        </div>
        <?php if (!empty($otpauthUri)): ?>
            <p class="form-help"><small>otpauth URI: <code style="word-break:break-all;"><?= e((string) $otpauthUri) ?></code></small></p>
        <?php endif; ?>
        <form method="post" action="<?= e(url($securityBase . '/totp/confirm')) ?>" class="form-stack">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label required" for="totp_code">Confirmation code</label>
                <input id="totp_code" name="code" required autocomplete="one-time-code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6">
            </div>
            <button type="submit" class="btn btn-primary">Enable authenticator</button>
        </form>
    <?php else: ?>
        <p class="text-muted">Not enabled. Protect your account with a time-based one-time password.</p>
        <form method="post" action="<?= e(url($securityBase . '/totp/begin')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Set up authenticator</button>
        </form>
    <?php endif; ?>
</section>

<section class="card form-stack" id="passkey-section"
    data-options-url="<?= e(url($securityBase . '/passkey/options')) ?>"
    data-register-url="<?= e(url($securityBase . '/passkey')) ?>"
    data-csrf="<?= e(csrf_token()) ?>">
    <h2>Passkeys</h2>
    <p class="text-muted">Use a platform authenticator (Face ID, Windows Hello, security key). </p>

    <?php if (!empty($passkeys)): ?>
        <ul class="form-stack">
            <?php foreach ($passkeys as $pk): ?>
                <li class="d-flex" style="justify-content:space-between;gap:1rem;align-items:center;">
                    <span>
                        <strong><?= e($pk['label'] ?? 'Passkey') ?></strong>
                        <small class="text-muted d-block">Added <?= e($pk['created_at'] ?? '') ?></small>
                    </span>
                    <form method="post" action="<?= e(url($securityBase . '/passkey/' . (int) $pk['id'] . '/delete')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondary btn-sm">Remove</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted">No passkeys registered.</p>
    <?php endif; ?>

    <div class="form-group">
        <label class="form-label" for="passkey_label">Label (optional)</label>
        <input id="passkey_label" type="text" maxlength="128" placeholder="Laptop / Phone">
    </div>
    <button type="button" class="btn btn-primary" id="register-passkey-btn">Register passkey</button>
    <p id="passkey-status" class="form-help" role="status"></p>
</section>

<script>
(function () {
    const root = document.getElementById('passkey-section');
    const btn = document.getElementById('register-passkey-btn');
    const status = document.getElementById('passkey-status');
    if (!root || !btn) return;

    function b64urlToBuf(s) {
        const pad = '='.repeat((4 - (s.length % 4)) % 4);
        const b64 = (s + pad).replace(/-/g, '+').replace(/_/g, '/');
        const bin = atob(b64);
        const buf = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
        return buf.buffer;
    }
    function bufToB64url(buf) {
        const bytes = new Uint8Array(buf);
        let s = '';
        for (let i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i]);
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    btn.addEventListener('click', async function () {
        if (!window.PublicKeyCredential) {
            status.textContent = 'Passkeys are not supported in this browser.';
            return;
        }
        status.textContent = 'Waiting for authenticator…';
        try {
            const optRes = await fetch(root.dataset.optionsUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            const optJson = await optRes.json();
            if (!optJson.ok) throw new Error(optJson.error || 'Could not start registration');
            const o = optJson.options;
            const publicKey = {
                challenge: b64urlToBuf(o.challenge),
                rp: o.rp,
                user: {
                    id: b64urlToBuf(o.user.id),
                    name: o.user.name,
                    displayName: o.user.displayName
                },
                pubKeyCredParams: o.pubKeyCredParams,
                timeout: o.timeout,
                attestation: o.attestation,
                authenticatorSelection: o.authenticatorSelection,
                excludeCredentials: (o.excludeCredentials || []).map(function (c) {
                    return { type: c.type, id: b64urlToBuf(c.id) };
                })
            };
            const cred = await navigator.credentials.create({ publicKey: publicKey });
            if (!cred) throw new Error('No credential returned');
            const att = cred.response;
            let publicKeyB64 = '';
            let algorithm = -7;
            if (typeof att.getPublicKey === 'function') {
                const pk = att.getPublicKey();
                if (pk) publicKeyB64 = bufToB64url(pk);
                if (typeof att.getPublicKeyAlgorithm === 'function') {
                    algorithm = att.getPublicKeyAlgorithm();
                }
            }
            if (!publicKeyB64) {
                throw new Error('Browser did not expose getPublicKey(). Use a current Chrome, Edge, Safari, or Firefox.');
            }
            const label = (document.getElementById('passkey_label') || {}).value || 'Passkey';
            const body = {
                id: cred.id,
                rawId: bufToB64url(cred.rawId),
                clientDataJSON: bufToB64url(att.clientDataJSON),
                publicKey: publicKeyB64,
                algorithm: algorithm,
                label: label
            };
            const reg = await fetch(root.dataset.registerUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': root.dataset.csrf
                },
                body: JSON.stringify(body)
            });
            const regJson = await reg.json();
            if (!regJson.ok) throw new Error(regJson.error || 'Registration failed');
            status.textContent = 'Passkey registered. Reloading…';
            window.location.reload();
        } catch (err) {
            status.textContent = err && err.message ? err.message : 'Passkey registration failed.';
        }
    });
})();
</script>
