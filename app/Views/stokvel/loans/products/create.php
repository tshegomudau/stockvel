<?php
use App\Services\Loans\LoanScheduleCalculator;
$methods = [
    LoanScheduleCalculator::METHOD_REDUCING_BALANCE => 'Reducing balance',
    LoanScheduleCalculator::METHOD_FLAT => 'Flat rate',
];
?>
<div class="card">
    <h1>New loan product</h1>
    <p class="text-muted"><a href="<?= e(url('/app/loans/products')) ?>">&larr; All products</a></p>
    <form method="post" action="<?= e(url('/app/loans/products')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group"><label for="name">Name</label><input type="text" id="name" name="name" value="<?= e((string) old('name', '')) ?>" required></div>
        <div class="form-row">
            <div class="form-group"><label for="min_amount">Minimum amount (ZAR)</label><input type="number" id="min_amount" name="min_amount" value="<?= e((string) old('min_amount', '')) ?>" required step="0.01" min="0"></div>
            <div class="form-group"><label for="max_amount">Maximum amount (ZAR)</label><input type="number" id="max_amount" name="max_amount" value="<?= e((string) old('max_amount', '')) ?>" required step="0.01" min="0"></div>
        </div>
        <div class="form-group"><label for="annual_interest_rate_percent">Annual interest rate (%)</label><input type="number" id="annual_interest_rate_percent" name="annual_interest_rate_percent" value="<?= e((string) old('annual_interest_rate_percent', '12')) ?>" required step="0.01" min="0"></div>
        <div class="form-row">
            <div class="form-group"><label for="min_term_months">Min term (months)</label><input type="number" id="min_term_months" name="min_term_months" value="<?= e((string) old('min_term_months', '1')) ?>" required min="1"></div>
            <div class="form-group"><label for="max_term_months">Max term (months)</label><input type="number" id="max_term_months" name="max_term_months" value="<?= e((string) old('max_term_months', '12')) ?>" required min="1"></div>
        </div>
        <div class="form-group">
            <label for="amortization_method">Amortization</label>
            <select id="amortization_method" name="amortization_method" required>
                <?php foreach ($methods as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= (string) old('amortization_method', LoanScheduleCalculator::METHOD_REDUCING_BALANCE) === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="form-group"><label for="instalment_fee">Instalment fee (ZAR)</label><input type="number" id="instalment_fee" name="instalment_fee" value="<?= e((string) old('instalment_fee', '0')) ?>" step="0.01" min="0"></div>
            <div class="form-group"><label for="penalty_rate_percent">Penalty rate (%)</label><input type="number" id="penalty_rate_percent" name="penalty_rate_percent" value="<?= e((string) old('penalty_rate_percent', '0')) ?>" step="0.01" min="0"></div>
        </div>
        <div class="form-group">
            <label class="checkbox"><input type="checkbox" name="requires_guarantors" value="1"<?= old('requires_guarantors') ? ' checked' : '' ?>> Requires guarantors</label>
        </div>
        <div class="form-group"><label for="min_guarantors">Minimum guarantors</label><input type="number" id="min_guarantors" name="min_guarantors" value="<?= e((string) old('min_guarantors', '0')) ?>" min="0"></div>
        <button type="submit" class="btn btn-primary">Save product</button>
    </form>
</div>
