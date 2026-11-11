<?php
$url = isset($url) && is_string($url) && $url !== '' ? $url : null;
$tag = $url !== null ? 'a' : 'div';
$class = 'stat-card' . ($url !== null ? ' stat-card-link' : '');
?>
<<?= $tag ?> class="<?= e($class) ?>"<?= $url !== null ? ' href="' . e($url) . '"' : '' ?>>
    <span class="stat-label"><?= e($label ?? '') ?></span>
    <strong class="stat-value<?= !empty($isMoney) ? ' money' : '' ?>"><?= e((string) ($value ?? '0')) ?></strong>
    <?php if (!empty($meta)): ?>
        <span class="stat-meta"><?= e($meta) ?></span>
    <?php endif; ?>
</<?= $tag ?>>
