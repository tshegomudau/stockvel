<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Stovel') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="layout-guest">
<div class="app-shell">
    <header class="topbar topbar--guest">
        <a class="brand" href="<?= e(url('/')) ?>">
            <span class="brand-mark" aria-hidden="true"></span>
            <span class="brand-text"><?= e(config('app.name', 'Stovel')) ?></span>
        </a>
    </header>
    <main class="main main--guest">
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success" role="alert"><?= e(is_string($msg) ? $msg : '') ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger" role="alert"><?= e(is_string($msg) ? $msg : '') ?></div>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <footer class="footer footer--guest">
        <p class="text-muted text-small">&copy; <?= date('Y') ?> Stovel. Not financial or legal advice.</p>
    </footer>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
