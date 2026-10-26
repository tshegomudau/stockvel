<?php $preview = $preview ?? null; ?>
<?= view('partials/page-header', [
    'title' => $preview ? 'Confirm bulk fines' : 'Bulk issue fines',
    'description' => $preview
        ? 'Review the list below, then confirm to issue.'
        : 'Issue the same fine to multiple members. You will confirm before anything is saved.',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/fines')) ?>">&larr; Fines</a></p>

<?php if ($preview): ?>
<div class="card mb-6">
    <h2>Preview</h2>
    <dl class="detail-list">
        <dt>Fine type</dt><dd><?= e($preview['fine_type_name'] ?? '') ?></dd>
        <dt>Amount</dt><dd class="money"><?= e(money($preview['amount'] ?? 0)) ?></dd>
        <dt>Due date</dt><dd><?= e(($preview['due_date'] ?? '') !== '' ? sa_date($preview['due_date']) : '—') ?></dd>
        <dt>Reason</dt><dd><?= e(($preview['reason'] ?? '') !== '' ? $preview['reason'] : '—') ?></dd>
        <dt>Members</dt><dd><?= (int) count($preview['members'] ?? []) ?></dd>
    </dl>
    <div class="table-wrap mt-4"><table class="table">
        <thead><tr><th>Member</th><th>Email</th></tr></thead>
        <tbody>
        <?php foreach ($preview['members'] ?? [] as $m): ?>
            <tr>
                <td><?= e(trim((string) ($m['member_name'] ?? '')) !== '' ? $m['member_name'] : '—') ?></td>
                <td><?= e($m['email'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <form method="post" action="<?= e(url('/app/fines/bulk')) ?>" class="form-stack mt-4">
        <?= csrf_field() ?>
        <input type="hidden" name="confirm" value="1">
        <input type="hidden" name="fine_type_id" value="<?= (int) ($preview['fine_type_id'] ?? 0) ?>">
        <input type="hidden" name="amount" value="<?= e((string) ($preview['amount'] ?? '')) ?>">
        <input type="hidden" name="reason" value="<?= e((string) ($preview['reason'] ?? '')) ?>">
        <input type="hidden" name="due_date" value="<?= e((string) ($preview['due_date'] ?? '')) ?>">
        <?php foreach ($preview['membership_ids'] ?? [] as $mid): ?>
            <input type="hidden" name="membership_ids[]" value="<?= (int) $mid ?>">
        <?php endforeach; ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" data-confirm="Issue these fines now?">Confirm &amp; issue</button>
            <a class="btn btn-secondary" href="<?= e(url('/app/fines/bulk')) ?>">Back</a>
        </div>
    </form>
</div>
<?php else: ?>
<form method="post" action="<?= e(url('/app/fines/bulk')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <input type="hidden" name="confirm" value="0">
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="fine_type_id">Fine type</label>
            <select id="fine_type_id" name="fine_type_id" required>
                <option value="">Select type</option>
                <?php foreach ($types ?? [] as $type): ?>
                    <option value="<?= (int) $type['id'] ?>" data-amount="<?= e((string) ($type['default_amount'] ?? '0')) ?>">
                        <?= e($type['name'] ?? '') ?> (<?= e(money($type['default_amount'] ?? 0)) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="amount">Amount (ZAR)</label>
            <input id="amount" name="amount" type="number" step="0.01" min="0.01" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="due_date">Due date (optional)</label>
            <input id="due_date" name="due_date" type="date">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="reason">Reason (optional)</label>
        <textarea id="reason" name="reason" rows="2"></textarea>
    </div>
    <div class="form-group">
        <label class="form-label">Members</label>
        <?php if (empty($members)): ?>
            <p class="text-muted">No active members.</p>
        <?php else: ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th></th><th>Member</th><th>Email</th></tr></thead>
                <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><input type="checkbox" name="membership_ids[]" value="<?= (int) $m['id'] ?>"></td>
                        <td><?= e(trim((string) ($m['member_name'] ?? '')) !== '' ? $m['member_name'] : '—') ?></td>
                        <td><?= e($m['email'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Preview</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/fines')) ?>">Cancel</a>
    </div>
</form>
<script>
(function () {
    var sel = document.getElementById('fine_type_id');
    var amt = document.getElementById('amount');
    if (!sel || !amt) return;
    sel.addEventListener('change', function () {
        var opt = sel.options[sel.selectedIndex];
        if (opt && opt.dataset.amount) amt.value = opt.dataset.amount;
    });
})();
</script>
<?php endif; ?>
