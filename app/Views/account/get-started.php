<?php
$requests = $requests ?? ($pendingRequests ?? []);
$hasOpenRequest = !empty($hasOpenRequest);
$statusLabels = [
    'pending' => 'Pending review',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'expired' => 'Expired',
    'claimed' => 'Claimed',
];
?>
<div class="auth-shell">
    <div class="auth-card card" style="max-width:40rem">
        <h1>Welcome<?= !empty($user['email']) ? ', ' . e((string) $user['email']) : '' ?></h1>
        <p class="lead">Your account is ready. Choose how you want to continue — you are not assigned to a stokvel yet.</p>

        <section class="mb-6">
            <h2 class="h3">Create a new stokvel</h2>
            <?php if ($hasOpenRequest): ?>
                <p>You already have a stokvel creation request in progress. Track it below — a new request cannot be started until this one is finished, rejected, or expired.</p>
            <?php else: ?>
                <p>Submit a request for platform review. When it is approved you become chairperson with full management permissions.</p>
                <a class="btn btn-primary" href="<?= e(url('/start-stokvel')) ?>">Start stokvel request</a>
            <?php endif; ?>
        </section>

        <section class="mb-6">
            <h2 class="h3">Join an existing stokvel</h2>
            <p>A committee member must invite the email address you registered with. Check your inbox for an invitation, then open the link to accept.</p>
            <p class="form-help">We cannot add you to a stokvel without an invitation.</p>
        </section>

        <?php if (!empty($requests)): ?>
        <section>
            <h2 class="h3">Your requests</h2>
            <ul class="form-stack" style="list-style:none;padding:0;margin:0;gap:.65rem">
                <?php foreach ($requests as $req): ?>
                    <?php $status = (string) ($req['status'] ?? ''); ?>
                    <li class="card" style="padding:.85rem 1rem;box-shadow:none">
                        <a href="<?= e(url('/stokvel-requests/' . rawurlencode((string) $req['public_token']))) ?>">
                            <?= e((string) $req['stokvel_name']) ?>
                        </a>
                        — <span class="badge"><?= e($statusLabels[$status] ?? $status) ?></span>
                        <?php if ($status === 'pending'): ?>
                            <p class="form-help" style="margin:.35rem 0 0">Platform admins have been notified. Refresh this page after approval to enter your stokvel as chairperson.</p>
                        <?php elseif ($status === 'approved'): ?>
                            <p class="form-help" style="margin:.35rem 0 0">Approved — refresh or reopen this page to open your dashboard as chairperson.</p>
                        <?php endif; ?>
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
