<?php $req = $request ?? []; ?>
<div class="auth-shell">
    <div class="auth-card card" style="max-width:36rem">
        <h1>Claim <?= e((string) ($req['stokvel_name'] ?? 'your stokvel')) ?></h1>
        <p class="lead">Email on the request: <?= e((string) ($req['requester_email'] ?? '')) ?></p>
        <form method="post" action="<?= e(url('/stokvel-claims/' . rawurlencode((string) $token))) ?>" class="form-stack">
            <?= csrf_field() ?>
            <?php if (!empty($existingAccount)): ?>
                <p class="form-help">An account already exists for this email. Enter your current password to claim chairperson access.</p>
                <div class="form-group">
                    <label class="form-label required" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" required minlength="8" autocomplete="current-password">
                </div>
            <?php else: ?>
                <p class="form-help">Create your chairperson password to finish setup.</p>
                <div class="form-group">
                    <label class="form-label" for="mobile">Mobile (optional)</label>
                    <input class="form-control" type="text" id="mobile" name="mobile" value="">
                </div>
                <div class="form-group">
                    <label class="form-label required" for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label class="form-label required" for="password_confirmation">Confirm password</label>
                    <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
                </div>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-block">Claim stokvel</button>
        </form>
    </div>
</div>
