<?php
$payment = $payment ?? [];
$pendingEdit = $pendingEdit ?? null;
$paymentMethods = $paymentMethods ?? \App\Services\Finance\ContributionService::PAYMENT_METHODS;
$methodLabels = [
    'cash' => 'Cash',
    'eft' => 'EFT',
    'bank_deposit' => 'Bank deposit',
    'debit_order' => 'Debit order (recorded)',
    'card' => 'Card',
    'other' => 'Other',
];
$paidDate = substr((string) ($payment['paid_at'] ?? date('Y-m-d')), 0, 10);
$currentMethod = (string) old('payment_method', $payment['payment_method'] ?? 'eft');
$currentMember = (string) old('membership_id', (string) ($payment['membership_id'] ?? ''));
?>
<?= view('partials/page-header', [
    'title' => 'Edit contribution',
    'description' => 'Changes are submitted for committee approval using contribution edit thresholds. The original payment stays unchanged until approved.',
], null) ?>

<?php if (!empty($pendingEdit)): ?>
    <div class="alert alert-warning mb-4" role="status">
        An edit for this contribution is already pending approval.
        <a href="<?= e(url('/app/approvals')) ?>">Open approvals</a>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <h2 class="h3">Current payment</h2>
    <dl class="detail-list">
        <div><dt>Receipt</dt><dd><?= e((string) ($payment['transaction_ref'] ?? $payment['receipt_number'] ?? '—')) ?></dd></div>
        <div><dt>Member</dt><dd><?= e($memberName !== '' ? $memberName : '—') ?></dd></div>
        <div><dt>Amount</dt><dd class="money"><?= e(money($payment['amount'] ?? 0)) ?></dd></div>
        <div><dt>Status</dt><dd><span class="badge"><?= e((string) ($payment['status'] ?? '')) ?></span></dd></div>
    </dl>
</div>

<form method="post" action="<?= e(url('/app/contributions/' . (int) ($payment['id'] ?? 0) . '/edit')) ?>" class="form-stack card"<?= !empty($pendingEdit) ? ' aria-disabled="true"' : '' ?>>
    <?= csrf_field() ?>
    <fieldset<?= !empty($pendingEdit) ? ' disabled' : '' ?>>
        <div class="form-group">
            <label class="form-label required" for="membership_id">Member</label>
            <select class="form-control form-select" id="membership_id" name="membership_id" required>
                <option value="">Select member</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?= e((string) $m['id']) ?>"<?= $currentMember === (string) $m['id'] ? ' selected' : '' ?>>
                        <?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label required" for="amount">Amount (ZAR)</label>
            <input class="form-control" type="number" step="0.01" min="0.01" id="amount" name="amount" required
                   value="<?= e((string) old('amount', $payment['amount'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label required" for="paid_at">Payment date</label>
            <input class="form-control" type="date" id="paid_at" name="paid_at" required
                   value="<?= e((string) old('paid_at', $paidDate)) ?>">
        </div>
        <div class="form-group">
            <label class="form-label required" for="payment_method">Method</label>
            <select class="form-control form-select" id="payment_method" name="payment_method" required>
                <?php foreach ($paymentMethods as $pm): ?>
                    <option value="<?= e($pm) ?>"<?= $currentMethod === $pm ? ' selected' : '' ?>>
                        <?= e($methodLabels[$pm] ?? ucwords(str_replace('_', ' ', $pm))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="reference">Reference</label>
            <input class="form-control" type="text" id="reference" name="reference"
                   value="<?= e((string) old('reference', $payment['external_reference'] ?? '')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label required" for="edit_reason">Reason for edit</label>
            <textarea class="form-control" id="edit_reason" name="edit_reason" rows="3" required><?= e((string) old('edit_reason')) ?></textarea>
            <p class="form-help">Shown to approvers. Amount/member changes rebuild allocations and ledger entries when approved.</p>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit for approval</button>
            <a class="btn btn-secondary" href="<?= e(url('/app/contributions')) ?>">Cancel</a>
        </div>
    </fieldset>
</form>
