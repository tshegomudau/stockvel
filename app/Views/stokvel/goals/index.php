<?= view('partials/page-header', [
    'title' => 'Saving goals',
    'description' => 'Management targets for internal funds. Progress is from recorded ledger/fund balances — not a bank account held by this application.',
], null) ?>
<?php
$goals = $goals ?? [];
$statusFilter = $statusFilter ?? '';
$canManage = !empty($canManage);
?>
<?php if ($canManage): ?>
<div class="page-actions mb-4">
    <a class="btn btn-primary" href="<?= e(url('/app/goals/create')) ?>">New goal</a>
</div>
<?php endif; ?>
<p class="mb-4">
    <a class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e(url('/app/goals')) ?>">All</a>
    <a class="btn btn-sm <?= $statusFilter === 'active' ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e(url('/app/goals?status=active')) ?>">Active</a>
    <a class="btn btn-sm <?= $statusFilter === 'achieved' ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e(url('/app/goals?status=achieved')) ?>">Achieved</a>
    <a class="btn btn-sm <?= $statusFilter === 'cancelled' ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e(url('/app/goals?status=cancelled')) ?>">Cancelled</a>
</p>
<?php if (empty($goals)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No saving goals',
        'emptyMessage' => 'Create a target such as December groceries or an emergency reserve.',
        'emptyActionUrl' => $canManage ? url('/app/goals/create') : null,
        'emptyActionLabel' => 'New goal',
    ], null) ?>
<?php else: ?>
<div class="stat-grid">
<?php foreach ($goals as $goal): ?>
    <article class="stat-card">
        <h2 class="h4"><a href="<?= e(url('/app/goals/' . (int) $goal['id'])) ?>"><?= e($goal['name'] ?? '') ?></a></h2>
        <p class="muted"><?= e($goal['description'] ?? '') ?></p>
        <p class="money"><?= e(money($goal['current_progress'] ?? 0)) ?> / <?= e(money($goal['target_amount'] ?? 0)) ?></p>
        <p><strong><?= (int) ($goal['progress_percent'] ?? 0) ?>%</strong>
            <span class="badge"><?= e((string) ($goal['status'] ?? '')) ?></span>
            <?php if (!empty($goal['fund_name'])): ?>
                · Linked fund: <?= e((string) $goal['fund_name']) ?>
            <?php else: ?>
                · Manual progress
            <?php endif; ?>
        </p>
        <?php if (!empty($goal['target_date'])): ?>
            <p class="text-sm">Target date: <?= e(sa_date($goal['target_date'])) ?></p>
        <?php endif; ?>
        <?php if ($canManage && ($goal['status'] ?? '') === 'active'): ?>
            <a class="btn btn-sm btn-ghost" href="<?= e(url('/app/goals/' . (int) $goal['id'] . '/edit')) ?>">Edit</a>
            <form method="post" action="<?= e(url('/app/goals/' . (int) $goal['id'] . '/status')) ?>" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="achieved">
                <button type="submit" class="btn btn-sm btn-secondary">Mark achieved</button>
            </form>
            <form method="post" action="<?= e(url('/app/goals/' . (int) $goal['id'] . '/status')) ?>" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="cancelled">
                <button type="submit" class="btn btn-sm btn-ghost" data-confirm="Cancel this goal?">Cancel</button>
            </form>
        <?php endif; ?>
    </article>
<?php endforeach; ?>
</div>
<?php endif; ?>
