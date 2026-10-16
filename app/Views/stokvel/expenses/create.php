<div class="card">
    <h1>New Expense</h1>
    <form method="post" action="<?= e(url('/app/expenses')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group"><label for="description">Description</label><input type="text" id="description" name="description" value="<?= e((string) old('description', (string) ($item['description'] ?? ''))) ?>" required></div>
        <div class="form-group"><label for="amount">Amount</label><input type="number" id="amount" name="amount" value="<?= e((string) old('amount', (string) ($item['amount'] ?? ''))) ?>" required step="0.01"></div>
        <div class="form-group"><label for="incurred_at">Date</label><input type="date" id="incurred_at" name="incurred_at" value="<?= e((string) old('incurred_at', (string) ($item['incurred_at'] ?? date('Y-m-d')))) ?>" required></div>
        <div class="form-group"><label for="vendor_name">Vendor (optional)</label><input type="text" id="vendor_name" name="vendor_name" value="<?= e((string) old('vendor_name', (string) ($item['vendor_name'] ?? ''))) ?>"></div>
        <div class="form-group">
            <label for="expense_category_id">Category</label>
            <select id="expense_category_id" name="expense_category_id" required>
                <option value="">Select category</option>
                <?php foreach ($categories ?? [] as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>" <?= (string) old('expense_category_id') === (string) $category['id'] ? 'selected' : '' ?>><?= e((string) $category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>
