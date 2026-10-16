<?php
/** @var string $title */
/** @var string|null $description */
/** @var string|null $actionUrl */
/** @var string|null $actionLabel */
?>
<div class="page-header">
    <div>
        <h1><?= e($title ?? '') ?></h1>
        <?php if (!empty($description)): ?>
            <p class="page-desc"><?= e($description) ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($actionUrl) && !empty($actionLabel)): ?>
        <div class="page-actions">
            <a class="btn btn-primary" href="<?= e($actionUrl) ?>"><?= e($actionLabel) ?></a>
        </div>
    <?php elseif (!empty($actionsHtml)): ?>
        <div class="page-actions"><?= $actionsHtml ?></div>
    <?php endif; ?>
</div>
