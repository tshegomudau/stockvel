<?php
$req = $request ?? [];
$status = (string) ($req['status'] ?? 'pending');
$labels = [
    'pending' => 'Pending review',
    'approved' => 'Approved — claim your account',
    'rejected' => 'Rejected',
    'expired' => 'Expired',
    'claimed' => 'Claimed',
];
?>
<div class="auth-shell">
    <div class="auth-card card" style="max-width:36rem">
        <h1>Request status</h1>
        <p><strong><?= e((string) ($req['stokvel_name'] ?? '')) ?></strong></p>
        <p>Status: <span class="badge"><?= e($labels[$status] ?? $status) ?></span></p>
        <?php if ($status === 'pending'): ?>
            <p>Platform administrators have been notified. You will receive an email when this request is reviewed.</p>
        <?php elseif ($status === 'approved'): ?>
            <p>Approved. Check the email sent to <?= e((string) ($req['requester_email'] ?? '')) ?> for your secure claim link<?= !empty($req['claim_expires_at']) ? ' (expires ' . e(sa_datetime((string) $req['claim_expires_at'])) . ')' : '' ?>.</p>
        <?php elseif ($status === 'rejected'): ?>
            <p>This request was not approved.<?= !empty($req['rejection_reason']) ? ' Reason: ' . e((string) $req['rejection_reason']) : '' ?></p>
            <a class="btn btn-secondary" href="<?= e(url('/start-stokvel')) ?>">Submit a new request</a>
        <?php elseif ($status === 'expired'): ?>
            <p>The claim link expired. Submit a new request or contact support.</p>
            <a class="btn btn-secondary" href="<?= e(url('/start-stokvel')) ?>">Submit again</a>
        <?php elseif ($status === 'claimed'): ?>
            <p>This stokvel has been claimed. <a href="<?= e(url('/login')) ?>">Sign in</a> to continue.</p>
        <?php endif; ?>
        <div class="auth-links mt-4">
            <a href="<?= e(url('/')) ?>">Home</a>
        </div>
    </div>
</div>
