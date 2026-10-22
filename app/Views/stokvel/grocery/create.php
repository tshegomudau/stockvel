<?= view('partials/page-header', [
    'title' => 'New grocery cycle',
    'description' => 'Set a target and shopping list for bulk buying.',
], null) ?>
<form method="post" action="<?= e(url('/app/grocery')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label" for="title">Cycle title</label>
        <input id="title" name="title" required placeholder="e.g. December 2026 hamper">
    </div>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="target_amount">Target pool (ZAR)</label>
            <input type="number" step="0.01" min="0" id="target_amount" name="target_amount">
        </div>
        <div class="form-group">
            <label class="form-label" for="distribution_date">Distribution date</label>
            <input type="date" id="distribution_date" name="distribution_date">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="2"></textarea>
    </div>
    <h3>Shopping list (optional)</h3>
    <?php for ($i = 0; $i < 5; $i++): ?>
        <div class="form-grid three-col">
            <input name="items[<?= $i ?>][item_name]" placeholder="Item">
            <input name="items[<?= $i ?>][quantity]" type="number" step="0.01" placeholder="Qty">
            <input name="items[<?= $i ?>][estimated_cost]" type="number" step="0.01" placeholder="Est. cost">
        </div>
    <?php endfor; ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create cycle</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/grocery')) ?>">Cancel</a>
    </div>
</form>
