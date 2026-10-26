<?php $goal = $goal ?? []; ?>
<?= view('partials/page-header', [
    'title' => (string) ($goal['name'] ?? 'Saving goal'),
    'description' => (string) ($goal['description'] ?? 'Progress against target'),
], null) ?>
<?php $canManage = !empty($canManage); ?>
<div class="toolbar mb-4">
    <?php if ($canManage): ?>
        <a class="btn btn-secondary" href="<?= e(url('/app/goals/' . (int) ($goal['id'] ?? 0) . '/edit')) ?>">Edit</a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(url('/app/goals')) ?>">All goals</a>
    <?php if ($canManage && ($goal['status'] ?? '') === 'active'): ?>
    <form method="post" action="<?= e(url('/app/goals/' . (int) $goal['id'] . '/cancel')) ?>" style="display:inline">
        <?= csrf_field() ?>
        <button class="btn btn-secondary" type="submit" data-confirm="Cancel this goal?">Cancel goal</button>
    </form>
    <?php endif; ?>
</div>
<div class="grid grid-3 mb-6">
    <?= view('partials/stat-card', ['label' => 'Target', 'value' => money($goal['target_amount'] ?? 0), 'isMoney' => true], null) ?>
    <?= view('partials/stat-card', ['label' => 'Progress', 'value' => money($goal['current_progress'] ?? 0), 'isMoney' => true, 'meta' => (int) ($goal['progress_percent'] ?? 0) . '%'], null) ?>
    <?= view('partials/stat-card', ['label' => 'Remaining', 'value' => money($goal['remaining'] ?? 0), 'isMoney' => true], null) ?>
</div>
<dl class="detail-list card">
    <dt>Status</dt><dd><span class="badge"><?= e($goal['status'] ?? '') ?></span></dd>
    <dt>Progress source</dt><dd><?= e(($goal['progress_source'] ?? '') === 'fund' ? 'Linked fund balance' : 'Manual progress amount') ?></dd>
    <dt>Fund</dt><dd><?= e($goal['fund_name'] ?? '—') ?></dd>
    <dt>Target date</dt><dd><?= e(sa_date($goal['target_date'] ?? '') ?: '—') ?></dd>
</dl>
