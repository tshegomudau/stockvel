<?php
/** @var \App\Core\Paginator|null $paginator */
if (!isset($paginator) || !$paginator->hasPages()) {
    return;
}
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($paginator->page > 1): ?>
        <a class="btn btn-sm" href="<?= e($paginator->url($paginator->page - 1)) ?>">Previous</a>
    <?php endif; ?>
    <span class="pagination-info">Page <?= (int) $paginator->page ?> of <?= (int) $paginator->lastPage() ?></span>
    <?php if ($paginator->page < $paginator->lastPage()): ?>
        <a class="btn btn-sm" href="<?= e($paginator->url($paginator->page + 1)) ?>">Next</a>
    <?php endif; ?>
</nav>
