<?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <ol>
        <?php foreach ($breadcrumbs as $i => $crumb): ?>
            <li>
                <?php if ($i < count($breadcrumbs) - 1 && !empty($crumb['url'])): ?>
                    <a href="<?= e($crumb['url']) ?>"><?= e($crumb['label']) ?></a>
                <?php else: ?>
                    <span aria-current="page"><?= e($crumb['label']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
