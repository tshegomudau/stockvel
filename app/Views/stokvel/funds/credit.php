<?= view('partials/page-header', [
    'title' => 'Credit fund',
    'description' => 'Add a credit to ' . ($fund['name'] ?? 'this fund') . '. Increases the recorded fund balance.',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/funds/' . (int) ($fund['id'] ?? 0))) ?>">&larr; Back to ledger</a></p>
<form method="post" action="<?= e(url('/app/funds/' . (int) ($fund['id'] ?? 0) . '/credit')) ?>" class="form-stack card">
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
    </div>
    <div class="form-group">
        <label class="form-label" for="description">Description</label>
        <input id="description" name="description" required value="<?= e(old('description', '')) ?>" placeholder="Allocation from contributions">
    </div>
    <div class="form-group">
        <label class="form-label" for="reference">Reference (optional)</label>
        <input id="reference" name="reference" value="<?= e(old('reference', '')) ?>">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Post credit</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/funds/' . (int) ($fund['id'] ?? 0))) ?>">Cancel</a>
    </div>
</form>
