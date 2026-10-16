<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Report') ?></title>
    <?= view('partials/styles', [], null) ?>
</head>
<body class="print-layout">
<div class="container print-container" style="padding:1.5rem 0;">
    <div class="no-print" style="margin-bottom:1rem;">
        <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
        <a class="btn btn-ghost" href="<?= e(url('/app/reports')) ?>">Back to reports</a>
    </div>
    <?= $content ?>
</div>
</body>
</html>
