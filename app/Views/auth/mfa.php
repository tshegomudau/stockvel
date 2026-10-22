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
    <div class="mt-6 form-stack">
        <button type="button" class="btn btn-secondary btn-block" id="mfa-passkey-btn">Use passkey</button>
        <p id="mfa-passkey-error" class="form-error" hidden></p>
    </div>
    <script>
    (function () {
        var btn = document.getElementById('mfa-passkey-btn');
        var err = document.getElementById('mfa-passkey-error');
        if (!btn || !window.PublicKeyCredential) {
            if (btn) btn.hidden = true;
            return;
        }
        function b64urlToBuf(s) {
            s = s.replace(/-/g, '+').replace(/_/g, '/');
            while (s.length % 4) s += '=';
            var bin = atob(s);
            var buf = new Uint8Array(bin.length);
            for (var i = 0; i < bin.length; i++) buf[i] = bin.charCodeAt(i);
            return buf.buffer;
        }
        function bufToB64url(buf) {
            var bytes = new Uint8Array(buf);
            var str = '';
            for (var i = 0; i < bytes.length; i++) str += String.fromCharCode(bytes[i]);
            return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        }
        btn.addEventListener('click', async function () {
            err.hidden = true;
            try {
                var optRes = await fetch(<?= json_encode(url('/login/mfa/passkey/options')) ?>, { credentials: 'same-origin' });
                var optJson = await optRes.json();
                if (!optJson.ok) throw new Error(optJson.error || 'Could not start passkey login');
                var o = optJson.options;
                o.challenge = b64urlToBuf(o.challenge);
                if (o.allowCredentials) {
                    o.allowCredentials = o.allowCredentials.map(function (c) {
                        c.id = b64urlToBuf(c.id);
                        return c;
                    });
                }
                var cred = await navigator.credentials.get({ publicKey: o });
                var body = {
                    id: cred.id,
                    rawId: bufToB64url(cred.rawId),
                    type: cred.type,
                    response: {
                        clientDataJSON: bufToB64url(cred.response.clientDataJSON),
                        authenticatorData: bufToB64url(cred.response.authenticatorData),
                        signature: bufToB64url(cred.response.signature),
                        userHandle: cred.response.userHandle ? bufToB64url(cred.response.userHandle) : null
                    }
                };
                var verifyRes = await fetch(<?= json_encode(url('/login/mfa/passkey')) ?>, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                            || document.querySelector('input[name="_token"]')?.value
                            || ''
                    },
                    body: JSON.stringify(body)
                });
                var verifyJson = await verifyRes.json();
                if (!verifyJson.ok) throw new Error(verifyJson.error || 'Passkey failed');
                window.location.href = verifyJson.redirect || <?= json_encode(url('/dashboard')) ?>;
            } catch (e) {
                err.textContent = e.message || 'Passkey sign-in failed';
                err.hidden = false;
            }
        });
    })();
    </script>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/login/mfa/cancel')) ?>" class="mt-6">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost btn-block">Cancel sign-in</button>
    </form>
</div>
