<div class="grid grid-3">
    <?php foreach ($reports as $key => $meta): ?>
        <a class="card report-card" href="<?= e(url('/app/reports/' . $key)) ?>">
            <h2><?= e($meta['title']) ?></h2>
            <p>View and export <?= e(strtolower($meta['title'])) ?> data.</p>
        </a>
    <?php endforeach; ?>
</div>
