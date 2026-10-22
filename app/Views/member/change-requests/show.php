<?php
$changeId = (int) ($change['id'] ?? 0);
$changeType = (string) ($change['change_type'] ?? '');
$isPending = ($change['status'] ?? '') === 'pending_member';
?>
<?= view('partials/page-header', [
    'title' => 'Review change',
    'description' => ucwords(str_replace('_', ' ', $changeType)),
], null) ?>

<div class="card mb-6">
    <dl class="detail-list">
        <dt>Status</dt><dd><?= e(ucfirst(str_replace('_', ' ', (string) ($change['status'] ?? '')))) ?></dd>
        <dt>Requested by</dt><dd><?= e($change['requester_email'] ?? '') ?></dd>
        <dt>Submitted</dt><dd><?= e(sa_datetime($change['created_at'] ?? '')) ?></dd>
    </dl>
</div>

<div class="card mb-6">
    <h2>Proposed changes</h2>
    <?php if ($changeType === 'profile_update'): ?>
        <dl class="detail-list">
            <dt>First name</dt><dd><?= e((string) ($payload['first_name'] ?? '')) ?></dd>
            <dt>Last name</dt><dd><?= e((string) ($payload['last_name'] ?? '')) ?></dd>
            <dt>Mobile</dt><dd><?= e((string) ($payload['mobile'] ?? '—')) ?></dd>
            <?php if (!empty($payload['id_number_masked']) || !empty($payload['id_number_encrypted'])): ?>
                <dt>ID number</dt><dd><?= e((string) ($payload['id_number_masked'] ?? '••••')) ?> (encrypted at rest)</dd>
            <?php else: ?>
                <dt>ID number</dt><dd><em>Unchanged</em></dd>
            <?php endif; ?>
        </dl>
    <?php elseif ($changeType === 'beneficiary_delete'): ?>
        <p>Remove beneficiary: <strong><?= e((string) ($payload['full_name'] ?? '')) ?></strong></p>
    <?php else: ?>
        <dl class="detail-list">
            <dt>Full name</dt><dd><?= e((string) ($payload['full_name'] ?? '')) ?></dd>
            <dt>Relationship</dt><dd><?= e((string) ($payload['relationship'] ?? '')) ?></dd>
            <dt>Share</dt><dd><?= e((string) ($payload['share_percent'] ?? '')) ?>%</dd>
            <dt>Phone</dt><dd><?= e((string) ($payload['phone'] ?? '—')) ?></dd>
            <dt>Email</dt><dd><?= e((string) ($payload['email'] ?? '—')) ?></dd>
            <dt>Primary</dt><dd><?= !empty($payload['is_primary']) ? 'Yes' : 'No' ?></dd>
            <?php if (!empty($payload['id_number_masked'])): ?>
                <dt>ID number</dt><dd><?= e((string) $payload['id_number_masked']) ?> (encrypted in request)</dd>
            <?php endif; ?>
        </dl>
    <?php endif; ?>
</div>

<?php if ($isPending): ?>
<div class="grid grid-2">
    <form method="post" action="<?= e(url('/member/change-requests/' . $changeId . '/approve')) ?>" class="card form-stack">
        <?= csrf_field() ?>
        <h2>Approve</h2>
        <div class="form-group">
            <label class="form-label" for="approve_notes">Notes (optional)</label>
            <textarea id="approve_notes" name="notes" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Approve and apply</button>
    </form>
    <form method="post" action="<?= e(url('/member/change-requests/' . $changeId . '/reject')) ?>" class="card form-stack">
        <?= csrf_field() ?>
        <h2>Reject</h2>
        <div class="form-group">
            <label class="form-label" for="reject_notes">Reason (optional)</label>
            <textarea id="reject_notes" name="notes" rows="2"></textarea>
        </div>
        <button type="submit" class="btn btn-danger">Reject</button>
    </form>
</div>
<?php endif; ?>

<p class="mt-4"><a href="<?= e(url('/member/change-requests')) ?>">Back to list</a></p>
