<nav class="breadcrumbs" aria-label="Breadcrumb">
    <ol>
        <li><a href="<?= e(url('/dashboard')) ?>">Home</a></li>
        <li aria-current="page">Dashboard</li>
    </ol>
</nav>

<h1 class="page-title">Welcome<?= isset($user['first_name']) ? ', ' . e($user['first_name']) : '' ?></h1>

<?php if (!$stokvel): ?>
    <div class="empty-state">
        <h2>No stokvel selected</h2>
        <p>Ask your chairperson for an invitation, or contact platform support.</p>
    </div>
<?php else: ?>
    <div class="stat-grid">
        <article class="stat-card">
            <p class="stat-label">Open charges</p>
            <p class="stat-value"><?= e((string) ($stats['open_charges'] ?? 0)) ?></p>
        </article>
        <article class="stat-card">
            <p class="stat-label">Active members</p>
            <p class="stat-value"><?= e((string) ($stats['members'] ?? 0)) ?></p>
        </article>
    </div>
    <div class="chart-container" id="dashboard-chart" data-chart='{"type":"bar","labels":["Jan","Feb","Mar","Apr"],"values":[1200,980,1100,1250]}'></div>
<?php endif; ?>
