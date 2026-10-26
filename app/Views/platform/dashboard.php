<?= view('partials/page-header', [
    'title' => 'Platform overview',
    'description' => 'Cross-tenant health for the stockvel platform.',
], null) ?>

<?php foreach (($alerts ?? []) as $alert): ?>
    <div class="alert alert-<?= e($alert['type'] ?? 'info') ?>" role="alert">
        <?= e($alert['message'] ?? '') ?>
        <?php if (!empty($alert['url'])): ?>
            <a href="<?= e($alert['url']) ?>"><?= e($alert['link_label'] ?? 'View') ?></a>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<div class="grid grid-4">
    <?= view('partials/stat-card', ['label' => 'Stokvels', 'value' => (string) ($metrics['stokvels'] ?? 0)], null) ?>
    <?= view('partials/stat-card', ['label' => 'Active', 'value' => (string) ($metrics['active_stokvels'] ?? 0)], null) ?>
    <?= view('partials/stat-card', ['label' => 'Suspended', 'value' => (string) ($metrics['suspended_stokvels'] ?? 0)], null) ?>
    <?= view('partials/stat-card', ['label' => 'Users', 'value' => (string) ($metrics['users'] ?? 0)], null) ?>
</div>

<div class="grid grid-3 mt-6">
    <?= view('partials/stat-card', ['label' => 'Failed jobs', 'value' => (string) ($metrics['failed_jobs'] ?? 0), 'meta' => 'Notification queue failures'], null) ?>
    <?= view('partials/stat-card', ['label' => 'Open tickets', 'value' => (string) ($metrics['open_ticket_count'] ?? 0), 'meta' => 'Support queue'], null) ?>
    <?= view('partials/stat-card', ['label' => 'Security events', 'value' => (string) ($metrics['security_events'] ?? 0), 'meta' => 'Auth-related audit entries'], null) ?>
</div>

<div class="quick-actions mt-6">
    <a class="btn btn-primary" href="<?= e(url('/platform/tenants')) ?>">Manage stokvels</a>
    <?php if (platform_can('audit_logs.view')): ?>
        <a class="btn btn-secondary" href="<?= e(url('/platform/audit')) ?>">Audit logs</a>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(url('/platform/jobs')) ?>">Failed jobs</a>
    <a class="btn btn-secondary" href="<?= e(url('/platform/support')) ?>">Support tickets</a>
</div>
