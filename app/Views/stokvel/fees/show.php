<?php $item = $item ?? []; ?>
<div class="page-header">
    <h1><?= e($item['name'] ?? ('Fee structure #' . ($item['id'] ?? ''))) ?></h1>
    <a class="btn" href="<?= e(url('/app/fees/' . ($item['id'] ?? '') . '/edit')) ?>">Edit</a>
</div>
<div class="card">
    <dl class="detail-list">
        <dt>Name</dt><dd><?= e($item['name'] ?? '') ?></dd>
        <dt>Amount</dt><dd class="money"><?= e(money($item['amount'] ?? 0)) ?></dd>
        <dt>Frequency</dt><dd><?= e(ucfirst((string) ($item['frequency'] ?? ''))) ?></dd>
        <dt>Status</dt><dd><span class="badge"><?= e($item['status'] ?? 'active') ?></span></dd>
        <dt>Effective from</dt><dd><?= e(sa_date($item['effective_from'] ?? $item['created_at'] ?? '')) ?></dd>
        <?php if (!empty($item['description'])): ?>
        <dt>Description</dt><dd><?= e($item['description']) ?></dd>
        <?php endif; ?>
    </dl>
    <form method="post" action="<?= e(url('/app/fees/' . ($item['id'] ?? ''))) ?>" onsubmit="return confirm('Remove this fee structure?');">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="btn btn-danger">Remove</button>
    </form>
</div>
