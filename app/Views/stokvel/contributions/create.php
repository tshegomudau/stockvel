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
            <select id="payment_method" name="payment_method">
                <?php foreach (['cash', 'eft', 'card', 'mobile'] as $pm): ?>
                    <option value="<?= e($pm) ?>"><?= e(strtoupper($pm)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="reference">Reference</label>
            <input type="text" id="reference" name="reference">
        </div>
        <button type="submit" class="btn btn-primary">Record payment</button>
    </form>
</div>
