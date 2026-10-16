<?= view('partials/page-header', [
    'title' => 'Platform overview',
    'description' => 'Cross-tenant health for the stockvel platform.',
], null) ?>

<div class="grid grid-4">
    <?= view('partials/stat-card', ['label' => 'Stokvels', 'value' => (string) ($metrics['stokvels'] ?? 0)], null) ?>
    <?= view('partials/stat-card', ['label' => 'Active', 'value' => (string) ($metrics['active_stokvels'] ?? 0)], null) ?>
    <?= view('partials/stat-card', ['label' => 'Suspended', 'value' => (string) ($metrics['suspended_stokvels'] ?? 0)], null) ?>
    <?= view('partials/stat-card', ['label' => 'Users', 'value' => (string) ($metrics['users'] ?? 0)], null) ?>
</div>

<div class="grid grid-2 mt-6">
    <?= view('partials/stat-card', ['label' => 'Failed jobs', 'value' => (string) ($metrics['failed_jobs'] ?? 0), 'meta' => 'Notification queue failures'], null) ?>
    <?= view('partials/stat-card', ['label' => 'Security events', 'value' => (string) ($metrics['security_events'] ?? 0), 'meta' => 'Auth-related audit entries'], null) ?>
</div>

<div class="quick-actions mt-6">
    <a class="btn btn-primary" href="<?= e(url('/platform/tenants')) ?>">Manage stokvels</a>
    <a class="btn btn-secondary" href="<?= e(url('/platform/audit')) ?>">Audit search</a>
    <a class="btn btn-secondary" href="<?= e(url('/platform/jobs')) ?>">Failed jobs</a>
</div>
