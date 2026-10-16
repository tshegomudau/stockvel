<div class="dashboard-hero">
    <div>
        <h1><?= e($stokvel['name'] ?? 'Stokvel dashboard') ?></h1>
        <p>Live figures from your ledger, memberships, and approvals.</p>
    </div>
    <div class="quick-actions">
        <a class="btn btn-primary" href="<?= e(url('/app/contributions/create')) ?>">Record contribution</a>
        <a class="btn btn-secondary" href="<?= e(url('/app/members/invite')) ?>">Invite member</a>
        <a class="btn btn-secondary" href="<?= e(url('/app/reports')) ?>">Reports</a>
    </div>
</div>

<div class="grid grid-4">
    <?= view('partials/stat-card', ['label' => 'Active members', 'value' => (string) ($stats['members'] ?? 0)], null) ?>
    <?= view('partials/stat-card', ['label' => 'Contributions (MTD)', 'value' => money($stats['contributions_mtd'] ?? 0), 'isMoney' => true], null) ?>
    <?= view('partials/stat-card', ['label' => 'Outstanding', 'value' => money($stats['arrears'] ?? 0), 'isMoney' => true, 'meta' => 'Unpaid charges'], null) ?>
    <?= view('partials/stat-card', ['label' => 'Pending approvals', 'value' => (string) ($stats['pending_approvals'] ?? 0)], null) ?>
</div>

<div class="grid grid-2 mt-6">
    <?= view('partials/stat-card', ['label' => 'Upcoming meetings', 'value' => (string) ($stats['upcoming_meetings'] ?? 0)], null) ?>
    <?= view('partials/stat-card', ['label' => 'Active loans', 'value' => (string) ($stats['active_loans'] ?? 0), 'meta' => 'Shown when lending is enabled'], null) ?>
</div>

<div class="panel-grid dashboard-main mt-6">
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

    <section class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Recent activity</h2>
                <p class="card-subtitle">Latest audited actions in this stokvel</p>
            </div>
        </div>
        <?php if (empty($recentActivity)): ?>
            <?= view('partials/empty', ['emptyTitle' => 'No activity yet', 'emptyMessage' => 'Member, finance, and settings changes will show here.'], null) ?>
        <?php else: ?>
            <ul class="activity-list">
                <?php foreach ($recentActivity as $row): ?>
                    <li class="activity-item">
                        <div>
                            <strong><?= e($row['action'] ?? '') ?></strong>
                            <div class="text-sm text-muted"><?= e($row['entity_type'] ?? '') ?></div>
                        </div>
                        <time datetime="<?= e($row['created_at'] ?? '') ?>"><?= e(sa_datetime($row['created_at'] ?? '')) ?></time>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
