<?php
$goal = $goal ?? null;
$isEdit = $goal !== null;
$funds = $funds ?? [];
?>
<?= view('partials/page-header', [
    'title' => $isEdit ? 'Edit saving goal' : 'New saving goal',
    'description' => 'Optional link to an internal fund. This is a management target, not a bank product.',
], null) ?>
<form method="post" action="<?= e($isEdit ? url('/app/goals/' . (int) $goal['id']) : url('/app/goals')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?><?= method_field('PUT') ?><?php endif; ?>
    <div class="form-group">
        <label class="form-label required" for="name">Name</label>
        <input id="name" name="name" required value="<?= e((string) old('name', (string) ($goal['name'] ?? ''))) ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" name="description" rows="3"><?= e((string) old('description', (string) ($goal['description'] ?? ''))) ?></textarea>
    </div>
    <div class="form-group">
        <label class="form-label required" for="target_amount">Target amount (ZAR)</label>
        <input id="target_amount" name="target_amount" inputmode="decimal" required
               value="<?= e((string) old('target_amount', (string) ($goal['target_amount'] ?? ''))) ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="target_date">Target date</label>
        <input type="date" id="target_date" name="target_date"
               value="<?= e((string) old('target_date', (string) ($goal['target_date'] ?? ''))) ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="fund_id">Linked internal fund (optional)</label>
        <select id="fund_id" name="fund_id">
            <option value="">None — use manual progress</option>
            <?php foreach ($funds as $fund): ?>
                <option value="<?= (int) $fund['id'] ?>"<?= (string) old('fund_id', (string) ($goal['fund_id'] ?? '')) === (string) $fund['id'] ? ' selected' : '' ?>>
                    <?= e(($fund['name'] ?? '') . (!empty($fund['code']) ? ' (' . $fund['code'] . ')' : '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label" for="progress_amount">Manual progress (if no fund linked)</label>
        <input id="progress_amount" name="progress_amount" inputmode="decimal"
               value="<?= e((string) old('progress_amount', (string) ($goal['progress_amount'] ?? '0'))) ?>">
    </div>
    <?php if ($isEdit): ?>
    <div class="form-group">
        <label class="form-label" for="status">Status</label>
        <select id="status" name="status">
            <?php foreach (['active', 'achieved', 'cancelled'] as $st): ?>
                <option value="<?= e($st) ?>"<?= (string) ($goal['status'] ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save goal' : 'Create goal' ?></button>
        <a class="btn btn-secondary" href="<?= e(url('/app/goals')) ?>">Cancel</a>
    </div>
</form>
