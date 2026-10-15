<div class="grid grid-4">
    <div class="stat-card card">
        <span class="stat-label">Stokvels</span>
        <strong class="stat-value"><?= e((string) ($metrics['stokvels'] ?? 0)) ?></strong>
    </div>
    <div class="stat-card card">
        <span class="stat-label">Active</span>
        <strong class="stat-value"><?= e((string) ($metrics['active_stokvels'] ?? 0)) ?></strong>
    </div>
    <div class="stat-card card">
        <span class="stat-label">Users</span>
        <strong class="stat-value"><?= e((string) ($metrics['users'] ?? 0)) ?></strong>
    </div>
    <div class="stat-card card">
        <span class="stat-label">Failed jobs</span>
        <strong class="stat-value"><?= e((string) ($metrics['failed_jobs'] ?? 0)) ?></strong>
    </div>
</div>
