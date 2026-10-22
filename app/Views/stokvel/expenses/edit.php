<div class="card">
    <h1>Edit Expense</h1>
    <form method="post" action="<?= e(url('/app/expenses/' . ($item['id'] ?? ''))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <div class="form-group"><label for="description">Description</label><input type="text" id="description" name="description" value="<?= e((string) old('description', (string) ($item['description'] ?? ''))) ?>" required></div>
        <div class="form-group"><label for="amount">Amount</label><input type="number" id="amount" name="amount" value="<?= e((string) old('amount', (string) ($item['amount'] ?? ''))) ?>" required step="0.01"></div>
        <div class="form-group"><label for="expense_date">Date</label><input type="date" id="expense_date" name="expense_date" value="<?= e((string) old('expense_date', (string) ($item['expense_date'] ?? ''))) ?>" required></div>
        <div class="form-group">
            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" disabled>
                <?php foreach ($categories ?? [] as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= (int) ($item['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
            <p class="form-help text-muted">Category cannot be changed after submission.</p>
        </div>
        <button type="submit" class="btn btn-primary">Update</button>
    </form>
</div>
