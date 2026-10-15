<div class="page-header">
    <h1>Rule #<?= e((string) ($item['id'] ?? '')) ?></h1>
    <a class="btn" href="<?= e(url('/app/rules/' . ($item['id'] ?? '') . '/edit')) ?>">Edit</a>
</div>
<div class="card">
    <dl class="detail-list">
    <?php foreach ($item as $key => $val): ?>
        <?php if (!is_scalar($val) && $val !== null) { continue; } ?>
        <dt><?= e((string) $key) ?></dt>
        <dd><?= e((string) ($val ?? '')) ?></dd>
    <?php endforeach; ?>
    </dl>
    <form method="post" action="<?= e(url('/app/rules/' . ($item['id'] ?? ''))) ?>" onsubmit="return confirm('Delete this record?');">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="btn btn-danger" data-confirm="Delete this Rule?">Delete</button>
    </form>
</div>
