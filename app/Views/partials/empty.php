<div class="empty-state">
    <h2><?= e($emptyTitle ?? 'Nothing here yet') ?></h2>
    <?php if (!empty($emptyMessage)): ?>
        <p><?= e($emptyMessage) ?></p>
    <?php endif; ?>
    <?php if (!empty($emptyActionUrl) && !empty($emptyActionLabel)): ?>
        <a class="btn btn-primary" href="<?= e($emptyActionUrl) ?>"><?= e($emptyActionLabel) ?></a>
    <?php endif; ?>
</div>
