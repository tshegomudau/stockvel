<?= view('partials/page-header', [
    'title' => 'Debit fund (admin expense)',
    'description' => 'Record spending from ' . ($fund['name'] ?? 'this pocket') . '. Negative balances are blocked unless the fund allows them.',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/funds/' . (int) ($fund['id'] ?? 0))) ?>">&larr; Back to ledger</a></p>
<form method="post" action="<?= e(url('/app/funds/' . (int) ($fund['id'] ?? 0) . '/debit')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="amount">Amount (ZAR)</label>
            <input id="amount" name="amount" type="number" step="0.01" min="0.01" required value="<?= e(old('amount', '')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="entry_date">Entry date</label>
            <input id="entry_date" name="entry_date" type="date" required value="<?= e(old('entry_date', date('Y-m-d'))) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="expense_category_id">Expense category</label>
            <select id="expense_category_id" name="expense_category_id">
                <option value="">— Optional —</option>
                <?php foreach ($categories ?? [] as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>"<?= (string) old('expense_category_id', '') === (string) $cat['id'] ? ' selected' : '' ?>>
                        <?= e($cat['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="category_name">Category label (optional)</label>
            <input id="category_name" name="category_name" value="<?= e(old('category_name', '')) ?>" placeholder="Stationery">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="description">Description</label>
        <input id="description" name="description" required value="<?= e(old('description', '')) ?>" placeholder="Printer paper">
    </div>
    <div class="form-group">
        <label class="form-label" for="reference">Reference (optional)</label>
        <input id="reference" name="reference" value="<?= e(old('reference', '')) ?>">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Post debit</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/funds/' . (int) ($fund['id'] ?? 0))) ?>">Cancel</a>
    </div>
</form>
