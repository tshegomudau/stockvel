<div class="stat-card">
    <span class="stat-label"><?= e($label ?? '') ?></span>
    <strong class="stat-value<?= !empty($isMoney) ? ' money' : '' ?>"><?= e((string) ($value ?? '0')) ?></strong>
    <?php if (!empty($meta)): ?>
        <span class="stat-meta"><?= e($meta) ?></span>
    <?php endif; ?>
</div>
