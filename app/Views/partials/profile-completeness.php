<?php
$completeness = $completeness ?? null;
if ($completeness === null) {
    return;
}
?>
<div class="card mb-6">
    <h2>Profile completeness</h2>
    <p><strong><?= (int) ($completeness['percent'] ?? 0) ?>%</strong>
        (<?= (int) ($completeness['complete'] ?? 0) ?> / <?= (int) ($completeness['total'] ?? 0) ?> factors)</p>
    <ul class="plain-list">
        <?php foreach (($completeness['factors'] ?? []) as $factor): ?>
            <li>
                <?= !empty($factor['done']) ? '✓' : '○' ?>
                <?= e($factor['label'] ?? '') ?>
                <?php if (empty($factor['done']) && !empty($factor['note'])): ?>
                    <span class="text-muted">— <?= e($factor['note']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (!empty($completeness['missing'])): ?>
        <p class="form-help">Missing: <?= e(implode('; ', $completeness['missing'])) ?></p>
    <?php endif; ?>
</div>
