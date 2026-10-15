<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <title><?= e($title ?? 'Report') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <style>@media print { .no-print { display: none; } }</style>
</head>
<body class="print-layout">
<div class="container print-container">
    <div class="no-print" style="margin-bottom:1rem;">
        <button type="button" class="btn" onclick="window.print()">Print</button>
    </div>
    <?= $content ?>
</div>
</body>
</html>
