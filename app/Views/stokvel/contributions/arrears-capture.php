<?= view('partials/page-header', [
    'title' => 'Capture arrears',
    'description' => 'Record a historical outstanding balance for a member (no fee structure required).',
], null) ?>

<form method="post" action="<?= e(url('/app/contributions/arrears/capture')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label required" for="member_id">Member</label>
        <select class="form-control form-select" id="member_id" name="member_id" required>
            <option value="">Select member</option>
            <?php foreach (($members ?? []) as $m): ?>
                <option value="<?= (int) ($m['id'] ?? 0) ?>"<?= (string) old('member_id') === (string) ($m['id'] ?? '') ? ' selected' : '' ?>>
                    <?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) . ' — ' . ($m['email'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label required" for="amount">Amount (ZAR)</label>
            <input class="form-control" id="amount" name="amount" type="number" step="0.01" min="0.01" required value="<?= e((string) old('amount')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label required" for="due_date">Due date</label>
            <input class="form-control" id="due_date" name="due_date" type="date" required value="<?= e((string) old('due_date', date('Y-m-d'))) ?>">
        </div>
    </div>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="period_label">Period label</label>
            <input class="form-control" type="text" id="period_label" name="period_label" placeholder="e.g. 2024-06" value="<?= e((string) old('period_label')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="description">Description</label>
            <input class="form-control" type="text" id="description" name="description" value="<?= e((string) old('description', 'Historical arrears')) ?>">
        </div>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save arrears</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/contributions/arrears')) ?>">Cancel</a>
    </div>
</form>
