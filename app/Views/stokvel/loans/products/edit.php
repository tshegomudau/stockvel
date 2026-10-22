<?php
use App\Services\Loans\LoanScheduleCalculator;
$methods = [
    LoanScheduleCalculator::METHOD_REDUCING_BALANCE => 'Reducing balance',
    LoanScheduleCalculator::METHOD_FLAT => 'Flat rate',
];
$active = !empty($item['is_active']) || (($item['status'] ?? '') === 'active');
?>
<div class="card">
    <h1>Edit loan product</h1>
    <p class="text-muted"><a href="<?= e(url('/app/loans/products')) ?>">&larr; All products</a></p>
    <form method="post" action="<?= e(url('/app/loans/products/' . ($item['id'] ?? ''))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <div class="form-group"><label for="name">Name</label><input type="text" id="name" name="name" value="<?= e((string) old('name', (string) ($item['name'] ?? ''))) ?>" required></div>
        <div class="form-row">
            <div class="form-group"><label for="min_amount">Minimum amount (ZAR)</label><input type="number" id="min_amount" name="min_amount" value="<?= e((string) old('min_amount', (string) ($item['min_amount'] ?? ''))) ?>" required step="0.01"></div>
            <div class="form-group"><label for="max_amount">Maximum amount (ZAR)</label><input type="number" id="max_amount" name="max_amount" value="<?= e((string) old('max_amount', (string) ($item['max_amount'] ?? ''))) ?>" required step="0.01"></div>
        </div>
        <div class="form-group"><label for="annual_interest_rate_percent">Annual interest rate (%)</label><input type="number" id="annual_interest_rate_percent" name="annual_interest_rate_percent" value="<?= e((string) old('annual_interest_rate_percent', (string) ($item['annual_interest_rate_percent'] ?? $item['interest_rate_percent'] ?? ''))) ?>" required step="0.01"></div>
        <div class="form-row">
            <div class="form-group"><label for="min_term_months">Min term (months)</label><input type="number" id="min_term_months" name="min_term_months" value="<?= e((string) old('min_term_months', (string) ($item['min_term_months'] ?? '1'))) ?>" required min="1"></div>
            <div class="form-group"><label for="max_term_months">Max term (months)</label><input type="number" id="max_term_months" name="max_term_months" value="<?= e((string) old('max_term_months', (string) ($item['max_term_months'] ?? ''))) ?>" required min="1"></div>
        </div>
        <div class="form-group">
            <label for="amortization_method">Amortization</label>
            <select id="amortization_method" name="amortization_method" required>
                <?php foreach ($methods as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= (string) old('amortization_method', (string) ($item['amortization_method'] ?? LoanScheduleCalculator::METHOD_REDUCING_BALANCE)) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="instalment_fee">Instalment fee (ZAR)</label><input type="number" id="instalment_fee" name="instalment_fee" value="<?= e((string) old('instalment_fee', (string) ($item['instalment_fee'] ?? '0'))) ?>" step="0.01"></div>
            <div class="form-group"><label for="penalty_rate_percent">Penalty rate (%)</label><input type="number" id="penalty_rate_percent" name="penalty_rate_percent" value="<?= e((string) old('penalty_rate_percent', (string) ($item['penalty_rate_percent'] ?? '0'))) ?>" step="0.01"></div>
        </div>
        <div class="form-group">
            <label class="checkbox"><input type="checkbox" name="requires_guarantors" value="1"<?= old('requires_guarantors', !empty($item['requires_guarantors'])) ? ' checked' : '' ?>> Requires guarantors</label>
        </div>
        <div class="form-group"><label for="min_guarantors">Minimum guarantors</label><input type="number" id="min_guarantors" name="min_guarantors" value="<?= e((string) old('min_guarantors', (string) ($item['min_guarantors'] ?? '0'))) ?>" min="0"></div>
        <div class="form-group">
            <label class="checkbox"><input type="checkbox" name="is_active" value="1"<?= old('is_active', $active) ? ' checked' : '' ?>> Product active (accept applications)</label>
        </div>
        <button type="submit" class="btn btn-primary">Update product</button>
    </form>
</div>
