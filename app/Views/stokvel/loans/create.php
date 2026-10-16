<div class="card">
    <h1>New Loan application</h1>
    <form method="post" action="<?= e(url('/app/loans')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="member_id">Member</label>
            <select id="member_id" name="member_id" required>
                <option value="">Select member</option>
                <?php foreach ($members ?? [] as $member): ?>
                    <option value="<?= e((string) $member['id']) ?>" <?= (string) old('member_id') === (string) $member['id'] ? 'selected' : '' ?>>
                        <?= e(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="loan_product_id">Product</label>
            <select id="loan_product_id" name="loan_product_id" required>
                <option value="">Select product</option>
                <?php foreach ($products ?? [] as $product): ?>
                    <option value="<?= e((string) $product['id']) ?>" <?= (string) old('loan_product_id') === (string) $product['id'] ? 'selected' : '' ?>>
                        <?= e((string) $product['name']) ?>
                        (<?= e((string) $product['min_amount']) ?>–<?= e((string) $product['max_amount']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label for="principal">Requested amount</label><input type="number" id="principal" name="principal" value="<?= e((string) old('principal', '')) ?>" required step="0.01"></div>
        <div class="form-group"><label for="term_months">Term (months)</label><input type="number" id="term_months" name="term_months" value="<?= e((string) old('term_months', '6')) ?>" required min="1"></div>
        <div class="form-group"><label for="purpose">Purpose</label><input type="text" id="purpose" name="purpose" value="<?= e((string) old('purpose', '')) ?>"></div>
        <button type="submit" class="btn btn-primary">Submit application</button>
    </form>
</div>
