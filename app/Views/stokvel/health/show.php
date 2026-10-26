<?= view('partials/page-header', [
    'title' => 'Group health',
    'description' => 'Operational indicators from your stokvel records. Not a credit rating or bureau score.',
], null) ?>
<?php $report = $report ?? ['score' => 0, 'band' => '', 'components' => [], 'recommendations' => [], 'disclaimer' => '']; ?>

<section class="card" aria-label="Overall health">
    <p class="stat-label">Overall health</p>
    <p class="h1" style="margin:0;"><?= (int) $report['score'] ?> / 100</p>
    <p><span class="badge"><?= e((string) ($report['band'] ?? '')) ?></span></p>
    <p class="muted"><?= e((string) ($report['disclaimer'] ?? '')) ?></p>
</section>

<section class="stat-grid mt-4" aria-label="Health components">
<?php foreach (($report['components'] ?? []) as $key => $c): ?>
    <div class="stat-card">
        <span class="stat-label"><?= e((string) ($c['label'] ?? $key)) ?></span>
        <strong><?= $c['included'] && $c['score'] !== null ? (int) $c['score'] . '%' : 'N/A' ?></strong>
        <p class="text-sm text-muted"><?= e((string) ($c['detail'] ?? '')) ?></p>
    </div>
<?php endforeach; ?>
</section>

<?php if (!empty($report['recommendations'])): ?>
<section class="card mt-4" aria-label="Recommendations">
    <h2 class="h3">Recommendations</h2>
    <ul>
        <?php foreach ($report['recommendations'] as $rec): ?>
            <li><?= e((string) $rec) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<p class="mt-4 muted">Scoring formula is documented for administrators in <code>docs/GROUP_HEALTH.md</code>.</p>
