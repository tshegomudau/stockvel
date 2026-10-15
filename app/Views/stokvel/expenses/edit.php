<div class="card">
    <h1>Edit Expense</h1>
    <form method="post" action="<?= e(url('/app/expenses/' . ($item['id'] ?? ''))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <div class="form-group"><label for="description">Description</label><input type="text" id="description" name="description" value="<?= e((string) old('description', (string) ($item['description'] ?? ''))) ?>" required></div>
        <div class="form-group"><label for="amount">Amount</label><input type="number" id="amount" name="amount" value="<?= e((string) old('amount', (string) ($item['amount'] ?? ''))) ?>" required step="0.01"></div>
        <div class="form-group"><label for="expense_date">Date</label><input type="date" id="expense_date" name="expense_date" value="<?= e((string) old('expense_date', (string) ($item['expense_date'] ?? ''))) ?>" required></div>
        <div class="form-group"><label for="category">Category</label><input type="text" id="category" name="category" value="<?= e((string) old('category', (string) ($item['category'] ?? ''))) ?>" required></div>
        <button type="submit" class="btn btn-primary">Update</button>
    </form>
</div>
