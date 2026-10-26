<div class="card">
    <h1>Capture contribution</h1>
    <form method="post" action="<?= e(url('/app/contributions')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="member_id">Member</label>
            <select id="member_id" name="member_id" required>
                <option value="">Select member</option>
                <?php foreach ($members as $m): ?>
                    <option value="<?= e((string) $m['id']) ?>"><?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="amount">Amount (ZAR)</label>
            <input type="number" step="0.01" min="0" id="amount" name="amount" required>
        </div>
        <div class="form-group">
            <label for="paid_at">Payment date</label>
            <input type="date" id="paid_at" name="paid_at" value="<?= e(date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
            <label for="payment_method">Method</label>
            <?php
            $paymentMethods = $paymentMethods ?? \App\Services\Finance\ContributionService::PAYMENT_METHODS;
            $methodLabels = [
                'cash' => 'Cash',
                'eft' => 'EFT',
                'bank_deposit' => 'Bank deposit',
                'debit_order' => 'Debit order (recorded)',
                'card' => 'Card',
                'other' => 'Other',
            ];
            ?>
            <select id="payment_method" name="payment_method" required>
                <?php foreach ($paymentMethods as $pm): ?>
                    <option value="<?= e($pm) ?>"<?= (string) old('payment_method', 'eft') === $pm ? ' selected' : '' ?>>
                        <?= e($methodLabels[$pm] ?? ucwords(str_replace('_', ' ', $pm))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="form-help">Recorded payment method only — this application does not execute bank payments.</p>
        </div>
        <div class="form-group">
            <label for="reference">Reference</label>
            <input type="text" id="reference" name="reference">
        </div>
        <button type="submit" class="btn btn-primary">Record payment</button>
    </form>
</div>
