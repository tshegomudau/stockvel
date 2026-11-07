<div class="auth-shell">
    <div class="auth-card card" style="max-width:40rem">
        <h1>Welcome<?= !empty($user['email']) ? ', ' . e((string) $user['email']) : '' ?></h1>
        <p class="lead">Your account is ready. Choose how you want to continue — you are not assigned to a stokvel yet.</p>

        <section class="mb-6">
            <h2 class="h3">Create a new stokvel</h2>
            <p>Submit a request for platform review. You will receive an email when it is approved, with a secure link to claim chairperson access.</p>
            <a class="btn btn-primary" href="<?= e(url('/start-stokvel')) ?>">Start stokvel request</a>
        </section>

        <section class="mb-6">
            <h2 class="h3">Join an existing stokvel</h2>
            <p>A committee member must invite the email address you registered with. Check your inbox for an invitation, then open the link to accept.</p>
            <p class="form-help">We cannot add you to a stokvel without an invitation — this keeps memberships secure.</p>
        </section>

        <?php if (!empty($pendingRequests)): ?>
        <section>
            <h2 class="h3">Your requests</h2>
            <ul>
                <?php foreach ($pendingRequests as $req): ?>
                    <li>
                        <a href="<?= e(url('/stokvel-requests/' . rawurlencode((string) $req['public_token']))) ?>">
                            <?= e((string) $req['stokvel_name']) ?>
                        </a>
                        — <span class="badge"><?= e((string) $req['status']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <div class="auth-links mt-4">
            <a href="<?= e(url('/logout')) ?>">Sign out</a>
        </div>
    </div>
</div>
