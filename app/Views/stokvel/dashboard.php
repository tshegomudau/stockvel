<?php
$can = $can ?? [];
$hasAnyStat = !empty($stats);
?>
<div class="dashboard-hero">
    <div>
        <h1><?= e($stokvel['name'] ?? 'Stokvel dashboard') ?></h1>
        <p>Live figures from your ledger, memberships, and approvals</p>
    </div>
    <div class="quick-actions">
        <?php if (!empty($can['capture'])): ?>
            <a class="btn btn-primary" href="<?= e(url('/app/contributions/create')) ?>">Record contribution</a>
        <?php endif; ?>
        <?php if (!empty($can['invite'])): ?>
            <a class="btn btn-secondary" href="<?= e(url('/app/members/invite')) ?>">Invite member</a>
        <?php endif; ?>
        <?php if (!empty($can['meetings'])): ?>
            <a class="btn btn-secondary" href="<?= e(url('/app/meetings/create')) ?>">Create meeting</a>
        <?php endif; ?>
        <?php if (!empty($can['approvals'])): ?>
            <a class="btn btn-secondary" href="<?= e(url('/app/approvals')) ?>">Review approvals</a>
        <?php endif; ?>
        <?php if (!empty($can['fees'])): ?>
            <a class="btn btn-secondary" href="<?= e(url('/app/funds')) ?>">Admin pocket</a>
        <?php endif; ?>
        <?php if (!empty($can['reports'])): ?>
            <a class="btn btn-secondary" href="<?= e(url('/app/reports')) ?>">Reports</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($hasAnyStat): ?>
<div class="grid grid-4">
    <?php if (array_key_exists('members', $stats)): ?>
        <?= view('partials/stat-card', ['label' => 'Active members', 'value' => (string) $stats['members']], null) ?>
    <?php endif; ?>
    <?php if (array_key_exists('contributions_mtd', $stats)): ?>
        <?= view('partials/stat-card', ['label' => 'Contributions (MTD)', 'value' => money($stats['contributions_mtd']), 'isMoney' => true], null) ?>
    <?php endif; ?>
    <?php if (array_key_exists('arrears', $stats)): ?>
        <?= view('partials/stat-card', ['label' => 'Outstanding', 'value' => money($stats['arrears']), 'isMoney' => true, 'meta' => 'Unpaid charges'], null) ?>
    <?php endif; ?>
    <?php if (array_key_exists('pending_approvals', $stats)): ?>
        <?= view('partials/stat-card', ['label' => 'Pending approvals', 'value' => (string) $stats['pending_approvals']], null) ?>
    <?php endif; ?>
</div>

<?php if (array_key_exists('upcoming_meetings', $stats) || array_key_exists('active_loans', $stats) || array_key_exists('admin_pocket_balance', $stats) || array_key_exists('group_health', $stats)): ?>
<div class="grid grid-4 mt-6">
    <?php if (array_key_exists('upcoming_meetings', $stats)): ?>
        <?= view('partials/stat-card', ['label' => 'Upcoming meetings', 'value' => (string) $stats['upcoming_meetings']], null) ?>
    <?php endif; ?>
    <?php if (array_key_exists('active_loans', $stats)): ?>
        <?= view('partials/stat-card', ['label' => 'Active loans', 'value' => (string) $stats['active_loans']], null) ?>
    <?php endif; ?>
    <?php if (array_key_exists('admin_pocket_balance', $stats)): ?>
        <?= view('partials/stat-card', ['label' => 'Admin pocket (ledger)', 'value' => money($stats['admin_pocket_balance']), 'isMoney' => true, 'meta' => 'Internal allocation'], null) ?>
    <?php endif; ?>
    <?php if (array_key_exists('group_health', $stats)): ?>
        <a href="<?= e(url('/app/health')) ?>" style="text-decoration:none;color:inherit;">
            <?= view('partials/stat-card', [
                'label' => 'Group health',
                'value' => (string) (int) $stats['group_health'] . ' / 100',
                'meta' => trim((string) (($healthScore['band'] ?? '') . ' — not a credit rating')),
            ], null) ?>
        </a>
    <?php endif; ?>

</div>
<?php endif; ?>
<?php else: ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No dashboard metrics for your role',
        'emptyMessage' => 'Use the member portal for your personal statement, or ask a committee member if you need admin access.',
    ], null) ?>
<?php endif; ?>

<div class="panel-grid dashboard-main mt-6">
    <?php if (!empty($can['contributions'])): ?>
    <section class="card chart-panel">
        <div class="card-header">
            <div>
                <h2 class="card-title">Contribution trend</h2>
                <p class="card-subtitle">Payments recorded in recent months</p>
            </div>
        </div>
        <?php if (!empty($chartLabels) && !empty($chartValues) && array_sum($chartValues) > 0): ?>
            <div class="chart-canvas" data-chart data-labels='<?= e(json_encode(array_values($chartLabels))) ?>' data-values='<?= e(json_encode(array_values($chartValues))) ?>' data-color="#14532d"></div>
        <?php else: ?>
            <?= view('partials/empty', [
                'emptyTitle' => 'No contribution data yet',
                'emptyMessage' => 'Once payments are recorded, the monthly trend will appear here.',
            ], null) ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!empty($can['approvals']) || !empty($can['contributions']) || !empty($can['meetings'])): ?>
    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Needs attention</h2>
                <p class="card-subtitle">Items within your permissions</p>
            </div>
        </div>
        <ul class="activity-list">
            <?php if (array_key_exists('pending_approvals', $stats)): ?>
            <li class="activity-item">
                <div><strong>Pending approvals</strong></div>
                <a href="<?= e(url('/app/approvals')) ?>"><?= (int) $stats['pending_approvals'] ?></a>
            </li>
            <?php endif; ?>
            <?php if (array_key_exists('arrears', $stats)): ?>
            <li class="activity-item">
                <div><strong>Outstanding arrears</strong></div>
                <a href="<?= e(url('/app/contributions/arrears')) ?>"><?= e(money($stats['arrears'])) ?></a>
            </li>
            <?php endif; ?>
            <?php if (array_key_exists('upcoming_meetings', $stats)): ?>
            <li class="activity-item">
                <div><strong>Upcoming meetings</strong></div>
                <a href="<?= e(url('/app/meetings')) ?>"><?= (int) $stats['upcoming_meetings'] ?></a>
            </li>
            <?php endif; ?>
        </ul>
    </section>
    <?php endif; ?>
</div>
