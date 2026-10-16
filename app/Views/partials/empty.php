<div class="empty-state">
    <div class="empty-illustration" aria-hidden="true">∅</div>
    <h3><?= e($emptyTitle ?? 'Nothing here yet') ?></h3>
    <p><?= e($emptyMessage ?? 'When records are available, they will appear in this space.') ?></p>
    <?php if (!empty($emptyActionUrl) && !empty($emptyActionLabel)): ?>
        <p style="margin-top:1rem;">
            <a class="btn btn-primary" href="<?= e($emptyActionUrl) ?>"><?= e($emptyActionLabel) ?></a>
        </p>
    <?php endif; ?>
</div>
