<div class="grid grid-4">
    <div class="stat-card card">
        <span class="stat-label">Active members</span>
        <strong class="stat-value"><?= e((string) ($stats['members'] ?? 0)) ?></strong>
    </div>
    <div class="stat-card card">
        <span class="stat-label">Contributions (MTD)</span>
        <strong class="stat-value"><?= e(money($stats['contributions_mtd'] ?? 0)) ?></strong>
    </div>
    <div class="stat-card card">
        <span class="stat-label">Arrears</span>
        <strong class="stat-value"><?= e(money($stats['arrears'] ?? 0)) ?></strong>
    </div>
    <div class="stat-card card">
        <span class="stat-label">Pending approvals</span>
        <strong class="stat-value"><?= e((string) ($stats['pending_approvals'] ?? 0)) ?></strong>
    </div>
</div>
<div class="card" style="margin-top:1.5rem;">
    <h2>Recent activity</h2>
    <?php if (empty($recentActivity)): ?>
        <?= view('partials/empty', ['emptyTitle' => 'No activity yet'], null) ?>
    <?php else: ?>
    <table class="table table-compact">
        <thead><tr><th>When</th><th>Action</th><th>Entity</th></tr></thead>
        <tbody>
        <?php foreach ($recentActivity as $row): ?>
            <tr>
                <td><?= e(sa_datetime($row['created_at'] ?? '')) ?></td>
                <td><?= e($row['action'] ?? '') ?></td>
                <td><?= e($row['entity_type'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
