<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Stovel') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="guest-layout">
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('/')) ?>">Stovel</a>
        <nav class="header-nav" aria-label="Primary">
            <a href="<?= e(url('/login')) ?>">Sign in</a>
            <a class="btn btn-primary" href="<?= e(url('/register')) ?>">Register</a>
        </nav>
    </div>
</header>
<main class="guest-main container">
    <?= view('partials/flash', [], null) ?>
    <?= $content ?>
</main>
<footer class="site-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> Stovel. Built for South African stokvels.</p>
    </div>
</footer>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
