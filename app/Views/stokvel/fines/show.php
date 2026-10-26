<?php
$fineId = (int) ($fine['id'] ?? 0);
$status = (string) ($fine['status'] ?? '');
$canWaive = in_array($status, ['outstanding', 'partially_paid'], true);
?>
<?= view('partials/page-header', [
    'title' => 'Fine #' . $fineId,
    'description' => (string) ($fine['fine_type_name_snapshot'] ?? 'Fine'),
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/fines')) ?>">&larr; All fines</a></p>

<div class="card mb-6">
    <dl class="detail-list">
        <dt>Member</dt>
        <dd>
            <a href="<?= e(url('/app/members/' . (int) ($fine['membership_id'] ?? 0))) ?>">
                <?= e(trim((string) ($fine['member_name'] ?? '')) !== '' ? $fine['member_name'] : ($fine['member_email'] ?? '—')) ?>
            </a>
        </dd>
        <dt>Type (snapshot)</dt><dd><?= e($fine['fine_type_name_snapshot'] ?? '') ?></dd>
        <dt>Amount</dt><dd class="money"><?= e(money($fine['amount'] ?? 0)) ?></dd>
        <dt>Paid</dt><dd class="money"><?= e(money($fine['amount_paid'] ?? 0)) ?></dd>
        <dt>Waived</dt><dd class="money"><?= e(money($fine['waived_amount'] ?? 0)) ?></dd>
        <dt>Status</dt><dd><span class="badge"><?= e(str_replace('_', ' ', $status)) ?></span></dd>
        <dt>Issued</dt><dd><?= e(sa_datetime($fine['issued_at'] ?? '')) ?></dd>
        <dt>Issued by</dt><dd><?= e(trim((string) ($fine['issued_by_name'] ?? '')) !== '' ? $fine['issued_by_name'] : '—') ?></dd>
        <dt>Due</dt><dd><?= e(sa_date($fine['due_date'] ?? '') ?: '—') ?></dd>
        <dt>Reason</dt><dd><?= e($fine['reason'] ?? '—') ?></dd>
        <?php if (!empty($fine['waived_at'])): ?>
            <dt>Waived at</dt><dd><?= e(sa_datetime($fine['waived_at'])) ?></dd>
            <dt>Waive reason</dt><dd><?= e($fine['waived_reason'] ?? '—') ?></dd>
        <?php endif; ?>
    </dl>
</div>

<?php if ($canWaive): ?>
<div class="card mb-6">
    <h2>Waive fine</h2>
    <form method="post" action="<?= e(url('/app/fines/' . $fineId . '/waive')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="waived_amount">Waive amount (optional — leave blank for full outstanding)</label>
            <input id="waived_amount" name="waived_amount" type="number" step="0.01" min="0.01">
        </div>
        <div class="form-group">
            <label class="form-label" for="waived_reason">Reason</label>
            <textarea id="waived_reason" name="waived_reason" rows="2" required></textarea>
        </div>
        <button type="submit" class="btn btn-danger" data-confirm="Waive this fine?">Waive</button>
    </form>
</div>
<?php endif; ?>
