<?php
$brandColour = $stokvel['primary_colour'] ?? '#0d6b4f';
?>
<!DOCTYPE html>
<html lang="en-ZA" style="--brand-primary: <?= e($brandColour) ?>; --brand-primary-dark: color-mix(in srgb, <?= e($brandColour) ?> 85%, black);">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Stovel') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-layout">
<button type="button" class="drawer-toggle no-desktop" aria-controls="app-drawer" aria-expanded="false" data-drawer-toggle>Menu</button>
<div class="app-shell">
    <aside class="sidebar desktop-only" aria-label="Sidebar">
        <?= view('partials/nav', get_defined_vars(), null) ?>
    </aside>
    <div id="app-drawer" class="drawer" hidden>
        <div class="drawer-panel">
            <?= view('partials/nav', get_defined_vars(), null) ?>
        </div>
    </div>
    <div class="app-main">
        <header class="app-topbar">
            <div class="topbar-inner">
                <?php if (!empty($breadcrumbs)): ?>
                    <?= view('partials/breadcrumbs', get_defined_vars(), null) ?>
                <?php else: ?>
                    <h1 class="page-title"><?= e($title ?? '') ?></h1>
                <?php endif; ?>
                <div class="topbar-actions">
                    <?php if (($navContext ?? 'app') === 'app'): ?>
                        <a class="btn btn-sm" href="<?= e(url('/app/onboarding')) ?>">Setup wizard</a>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        <main class="app-content container">
            <?= view('partials/flash', [], null) ?>
            <?= $content ?>
        </main>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
