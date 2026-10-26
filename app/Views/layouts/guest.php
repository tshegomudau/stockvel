<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(($title ?? 'stockvel') . ' · stockvel') ?></title>
    <?= view('partials/styles', [], null) ?>
</head>
<body class="guest-layout">
<a class="skip-link" href="#main-content">Skip to content</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand-lockup" href="<?= e(url('/')) ?>" aria-label="stockvel home">
            <span class="brand-mark" aria-hidden="true">S</span>
            <span class="brand-wordmark">stockvel</span>
        </a>
        <nav class="header-nav" aria-label="Primary">
            <a href="<?= e(url('/login')) ?>">Sign in</a>
            <a class="btn btn-primary" href="<?= e(url('/register')) ?>">Register</a>
        </nav>
    </div>
</header>
<main id="main-content" class="guest-main" tabindex="-1">
    <div class="container">
        <?= view('partials/flash', [], null) ?>
        <?= $content ?>
    </div>
</main>
<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> stockvel. Built for South African stokvels.</p>
    </div>
</footer>
<div class="toast-stack" data-toast-stack aria-live="polite"></div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
