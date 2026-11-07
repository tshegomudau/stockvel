<?php $row = $requestRow ?? []; ?>
<?= view('partials/page-header', [
    'title' => 'Review: ' . ($row['stokvel_name'] ?? 'Request'),
    'description' => 'Approve to create the stokvel and email a claim link, or reject with a reason.',
], null) ?>
<article class="card form-stack">
    <dl class="detail-list">
        <dt>Status</dt><dd><span class="badge"><?= e($row['status'] ?? '') ?></span></dd>
        <dt>Requester</dt><dd><?= e(trim(($row['requester_first_name'] ?? '') . ' ' . ($row['requester_last_name'] ?? ''))) ?> · <?= e($row['requester_email'] ?? '') ?></dd>
        <dt>Type</dt><dd><?= e($row['stokvel_type'] ?? '') ?></dd>
        <dt>Description</dt><dd><?= e($row['description'] ?? '—') ?></dd>
        <dt>Submitted</dt><dd><?= e(sa_datetime($row['created_at'] ?? '')) ?></dd>
        <dt>Public status</dt><dd><a href="<?= e(url('/stokvel-requests/' . rawurlencode((string) ($row['public_token'] ?? '')))) ?>">Open</a></dd>
    </dl>
    <?php if (($row['status'] ?? '') === 'pending' && !empty($canApprove)): ?>
        <form method="post" action="<?= e(url('/platform/stokvel-requests/' . (int) $row['id'] . '/approve')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" data-confirm="Approve this request and email a claim link?">Approve</button>
        </form>
        <form method="post" action="<?= e(url('/platform/stokvel-requests/' . (int) $row['id'] . '/reject')) ?>" class="form-stack mt-4">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="reason">Rejection reason (optional)</label>
                <textarea class="form-control form-textarea" id="reason" name="reason" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-danger" data-confirm="Reject this request?">Reject</button>
        </form>
    <?php endif; ?>
    <p class="mt-4"><a href="<?= e(url('/platform/stokvel-requests')) ?>">← All requests</a></p>
</article>
