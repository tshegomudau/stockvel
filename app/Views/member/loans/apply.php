<div class="card">
    <h1>Apply for a loan</h1>
    <div class="alert alert-warning">Loan decisions are made by your stokvel committee. Submitting an application does not guarantee approval.</div>
    <?php if (empty($products)): ?>
        <p>No loan products are available right now. Contact your committee.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/member/loans/apply')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="loan_product_id">Loan product</label>
            <select id="loan_product_id" name="loan_product_id" required>
                <option value="">Select product</option>
                <?php foreach ($products as $product): ?>
                    <?php
                        $needsG = !empty($product['requires_guarantors']) || !empty($product['requires_guarantor']);
                        $minG = (int) ($product['min_guarantors'] ?? ($needsG ? 1 : 0));
                    ?>
                    <option
                        value="<?= e((string) $product['id']) ?>"
                        data-requires-guarantors="<?= $needsG ? '1' : '0' ?>"
                        data-min-guarantors="<?= $minG ?>"
                        <?= (string) old('loan_product_id') === (string) $product['id'] ? 'selected' : '' ?>
                    >
                        <?= e((string) $product['name']) ?>
                        (<?= e(money($product['min_amount'] ?? 0)) ?>–<?= e(money($product['max_amount'] ?? 0)) ?>,
                        <?= e((string) ($product['annual_interest_rate_percent'] ?? $product['interest_rate_percent'] ?? '0')) ?>% p.a.)
                        <?= $needsG ? ' · needs ' . $minG . '+ guarantor(s)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label for="principal">Amount requested (ZAR)</label><input type="number" id="principal" name="principal" value="<?= e((string) old('principal', '')) ?>" required step="0.01" min="0"></div>
        <div class="form-group"><label for="term_months">Repayment term (months)</label><input type="number" id="term_months" name="term_months" value="<?= e((string) old('term_months', '6')) ?>" required min="1"></div>
        <div class="form-group"><label for="purpose">Purpose (optional)</label><input type="text" id="purpose" name="purpose" value="<?= e((string) old('purpose', '')) ?>" maxlength="255"></div>

        <?php if (!empty($guarantors)): ?>
        <fieldset class="form-section">
            <legend>Guarantors</legend>
            <p class="form-help">Select fellow members if your product requires guarantors.</p>
            <div class="permission-grid">
                <?php
                $oldGuarantors = old('guarantor_ids', []);
                if (!is_array($oldGuarantors)) {
                    $oldGuarantors = [];
                }
                foreach ($guarantors as $g):
                    $gid = (int) $g['id'];
                ?>
                    <label class="checkbox">
                        <input type="checkbox" name="guarantor_ids[]" value="<?= $gid ?>"<?= in_array($gid, array_map('intval', $oldGuarantors), true) ? ' checked' : '' ?>>
                        <?= e(trim(($g['first_name'] ?? '') . ' ' . ($g['last_name'] ?? ''))) ?>
                        <?php if (!empty($g['member_number'])): ?>
                            <span class="text-muted">(<?= e($g['member_number']) ?>)</span>
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php endif; ?>

        <button type="submit" class="btn btn-primary">Submit application</button>
    </form>
    <?php endif; ?>
</div>
