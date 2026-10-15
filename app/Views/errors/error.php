<div class="card error-card">
    <h1><?= e((string) ($code ?? 'Error')) ?></h1>
    <p><?= e($message ?? 'Something went wrong.') ?></p>
    <a class="btn btn-primary" href="<?= e(url('/')) ?>">Go home</a>
</div>
