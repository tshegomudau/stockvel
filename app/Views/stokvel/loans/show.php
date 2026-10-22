<?php
$status = (string) ($item['status'] ?? '');
$isApplication = isset($item['application_ref']) || !isset($item['loan_ref']);
$memberName = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
?>
<div class="page-header">
    <h1><?= $isApplication ? 'Application' : 'Loan' ?> <?= e($item['application_ref'] ?? $item['loan_ref'] ?? ('#' . ($item['id'] ?? ''))) ?></h1>
    <?php if ($isApplication && $status === 'submitted'): ?>
        <a class="btn btn-secondary" href="<?= e(url('/app/loans/' . ($item['id'] ?? '') . '/edit')) ?>">Edit purpose</a>
    <?php endif; ?>
</div>
<div class="alert alert-warning"><?= e($regulatoryNotice ?? '') ?></div>
<div class="card">
    <dl class="detail-list">
        <dt>Member</dt>
        <dd><?= e($memberName !== '' ? $memberName : ('Member #' . ($item['member_id'] ?? $item['membership_id'] ?? ''))) ?></dd>
        <dt>Product</dt>
        <dd><?= e($item['product_name'] ?? '—') ?></dd>
        <dt>Amount</dt>
        <dd class="money"><?= e(money($item['requested_amount'] ?? $item['principal_amount'] ?? 0)) ?></dd>
        <dt>Term</dt>
        <dd><?= e((string) ($item['term_months'] ?? '—')) ?> months</dd>
        <dt>Status</dt>
        <dd><span class="badge"><?= e($status) ?></span></dd>
        <?php if (!empty($item['purpose'])): ?>
        <dt>Purpose</dt>
        <dd><?= e((string) $item['purpose']) ?></dd>
        <?php endif; ?>
        <?php if (!empty($item['submitted_at'])): ?>
        <dt>Submitted</dt>
        <dd><?= e((string) $item['submitted_at']) ?></dd>
        <?php endif; ?>
        <?php if (!empty($item['decided_at'])): ?>
        <dt>Decided</dt>
        <dd><?= e((string) $item['decided_at']) ?></dd>
        <?php endif; ?>
        <?php if (!empty($item['decision_notes'])): ?>
        <dt>Decision notes</dt>
        <dd><?= e((string) $item['decision_notes']) ?></dd>
        <?php endif; ?>
        <?php if (!empty($loanRecord)): ?>
        <dt>Disbursed loan</dt>
        <dd><?= e($loanRecord['loan_ref'] ?? ('Loan #' . $loanRecord['id'])) ?> — outstanding <?= e(money($loanRecord['outstanding_balance'] ?? 0)) ?></dd>
        <?php endif; ?>
        <?php if (!empty($approvalRequest)): ?>
        <dt>Approval queue</dt>
        <dd><span class="badge"><?= e((string) ($approvalRequest['status'] ?? '')) ?></span></dd>
        <?php endif; ?>
    </dl>
</div>

<?php if ($isApplication && $status === 'submitted'): ?>
<div class="card">
    <h2>Committee decision</h2>
    <form method="post" action="<?= e(url('/app/loans/' . ($item['id'] ?? '') . '/approve')) ?>" class="form-stack" style="margin-bottom:1.5rem">
        <?= csrf_field() ?>
        <div class="form-group"><label for="approved_amount">Approved amount (optional, defaults to requested)</label><input type="number" step="0.01" id="approved_amount" name="approved_amount" value="<?= e((string) ($item['requested_amount'] ?? '')) ?>"></div>
        <div class="form-group"><label for="approve_notes">Notes</label><input type="text" id="approve_notes" name="notes" maxlength="500"></div>
        <button type="submit" class="btn btn-primary">Approve application</button>
    </form>
    <form method="post" action="<?= e(url('/app/loans/' . ($item['id'] ?? '') . '/reject')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group"><label for="reject_notes">Rejection reason</label><input type="text" id="reject_notes" name="notes" required maxlength="500"></div>
        <button type="submit" class="btn btn-secondary" data-confirm="Reject this application?">Reject</button>
    </form>
</div>
<?php endif; ?>

<?php if ($isApplication && $status === 'approved' && empty($loanRecord)): ?>
<div class="card">
    <h2>Disbursement</h2>
    <p>Committee has approved this application. Disbursement posts ledger entries and activates the repayment schedule.</p>
    <form method="post" action="<?= e(url('/app/loans/' . ($item['id'] ?? '') . '/disburse')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary" data-confirm="Disburse this loan now?">Disburse loan</button>
    </form>
</div>
<?php endif; ?>

<?php if ($isApplication && in_array($status, ['submitted', 'approved'], true)): ?>
<div class="card">
    <form method="post" action="<?= e(url('/app/loans/' . ($item['id'] ?? ''))) ?>" onsubmit="return confirm('Cancel this application?');">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="btn btn-danger" data-confirm="Cancel this application?">Cancel application</button>
    </form>
</div>
<?php endif; ?>
