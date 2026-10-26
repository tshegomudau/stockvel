<?= view('partials/page-header', [
    'title' => 'Issue fine',
    'description' => 'Record a fine against a member. The fine type name is snapshotted at issue time.',
], null) ?>
<form method="post" action="<?= e(url('/app/fines')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label" for="membership_id">Member</label>
        <select id="membership_id" name="membership_id" required>
            <option value="">Select member</option>
            <?php foreach ($members ?? [] as $m): ?>
                <?php $mid = (int) ($m['id'] ?? 0); ?>
                <option value="<?= $mid ?>"<?= (string) old('membership_id', (string) ($preselectMembershipId ?? '')) === (string) $mid ? ' selected' : '' ?>>
                    <?= e(trim((string) ($m['member_name'] ?? '')) !== '' ? $m['member_name'] : ($m['email'] ?? 'Member #' . $mid)) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="fine_type_id">Fine type</label>
            <select id="fine_type_id" name="fine_type_id" required>
                <option value="">Select type</option>
                <?php foreach ($types ?? [] as $type): ?>
                    <option value="<?= (int) $type['id'] ?>"
                            data-amount="<?= e((string) ($type['default_amount'] ?? '0')) ?>"
                            <?= (string) old('fine_type_id', '') === (string) $type['id'] ? ' selected' : '' ?>>
                        <?= e($type['name'] ?? '') ?> (<?= e(money($type['default_amount'] ?? 0)) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($types)): ?>
                <p class="form-help"><a href="<?= e(url('/app/fine-types')) ?>">Create a fine type</a> first.</p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label" for="amount">Amount (ZAR)</label>
            <input id="amount" name="amount" type="number" step="0.01" min="0.01" required value="<?= e(old('amount', '')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="due_date">Due date (optional)</label>
            <input id="due_date" name="due_date" type="date" value="<?= e(old('due_date', '')) ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="reason">Reason (optional)</label>
        <textarea id="reason" name="reason" rows="3"><?= e(old('reason', '')) ?></textarea>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Issue fine</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/fines')) ?>">Cancel</a>
    </div>
</form>
<script>
(function () {
    var sel = document.getElementById('fine_type_id');
    var amt = document.getElementById('amount');
    if (!sel || !amt) return;
    var touched = amt.value !== '';
    amt.addEventListener('input', function () { touched = true; });
    function applyDefault(force) {
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value || opt.dataset.amount === undefined) return;
        if (force || !touched || amt.value === '' || amt.value === '0') {
            amt.value = opt.dataset.amount;
            touched = false;
        }
    }
    sel.addEventListener('change', function () { applyDefault(true); });
    if (sel.value) applyDefault(false);
})();
</script>
