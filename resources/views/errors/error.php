<section class="card card-auth">
    <h1 class="card-title">Error <?= e((string) ($status ?? 500)) ?></h1>
    <p><?= e($message ?? 'Something went wrong.') ?></p>
    <?php if (!empty($debug) && $debug instanceof Throwable): ?>
        <pre class="debug-trace"><?= e($debug->getMessage()) ?></pre>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(url('/')) ?>">Back home</a>
</section>
