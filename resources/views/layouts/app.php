<?php
$stokvel = $stokvel ?? null;
$themePrimary = $stokvel['theme_primary'] ?? '#0d5c63';
$themeAccent = $stokvel['theme_accent'] ?? '#e8c547';
?>
<!DOCTYPE html>
<html lang="en-ZA" data-stokvel-theme style="--brand: <?= e($themePrimary) ?>; --brand-accent: <?= e($themeAccent) ?>;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Dashboard') ?> — <?= e(config('app.name', 'stockvel')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="layout-app">
<div class="app-shell" id="app-shell">
    <div class="sidebar-backdrop" data-drawer-close hidden></div>
    <aside class="sidebar" id="app-sidebar" aria-label="Main navigation">
        <div class="sidebar-header">
            <a class="brand brand--sidebar" href="<?= e(url('/dashboard')) ?>">
                <span class="brand-mark" aria-hidden="true"></span>
                <span class="brand-text"><?= e(config('app.name', 'stockvel')) ?></span>
            </a>
        </div>
        <nav class="sidebar-nav">
            <a class="sidebar-link is-active" href="<?= e(url('/dashboard')) ?>">Dashboard</a>
        </nav>
        <?php if ($stokvel): ?>
            <div class="sidebar-footer">
                <p class="text-small text-muted">Active stokvel</p>
                <p class="sidebar-stokvel-name"><?= e($stokvel['name']) ?></p>
            </div>
        <?php endif; ?>
    </aside>
    <div class="main-column">
        <header class="topbar">
            <button type="button" class="btn btn-ghost btn-icon" data-drawer-toggle aria-controls="app-sidebar" aria-expanded="false">
                <span class="sr-only">Menu</span>
                <svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z"/></svg>
            </button>
            <div class="topbar-actions">
                <form method="post" action="<?= e(url('/logout')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
                </form>
            </div>
        </header>
        <main class="main">
            <?php if ($msg = flash('success')): ?>
                <div class="alert alert-success" role="alert"><?= e(is_string($msg) ? $msg : '') ?></div>
            <?php endif; ?>
            <?php if ($msg = flash('error')): ?>
                <div class="alert alert-danger" role="alert"><?= e(is_string($msg) ? $msg : '') ?></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
