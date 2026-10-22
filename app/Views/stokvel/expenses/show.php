<?php $item = $item ?? []; ?>
<div class="page-header">
    <h1>Expense</h1>
    <a class="btn" href="<?= e(url('/app/expenses/' . ($item['id'] ?? '') . '/edit')) ?>">Edit</a>
</div>
<div class="card">
    <dl class="detail-list">
        <dt>Description</dt><dd><?= e($item['description'] ?? '') ?></dd>
        <dt>Category</dt><dd><?= e($item['category_name'] ?? $item['category_code'] ?? '—') ?></dd>
        <dt>Amount</dt><dd class="money"><?= e(money($item['amount'] ?? 0)) ?></dd>
        <dt>Expense date</dt><dd><?= e(sa_date($item['incurred_at'] ?? $item['expense_date'] ?? '')) ?></dd>
        <dt>Status</dt><dd><span class="badge"><?= e(ucfirst((string) ($item['status'] ?? ''))) ?></span></dd>
        <?php if (!empty($item['vendor_name'])): ?>
        <dt>Vendor</dt><dd><?= e($item['vendor_name']) ?></dd>
        <?php endif; ?>
        <dt>Captured by</dt><dd><?= e(trim((string) ($item['captured_by_name'] ?? '')) !== '' ? $item['captured_by_name'] : '—') ?></dd>
        <dt>Recorded</dt><dd><?= e(sa_datetime($item['created_at'] ?? '')) ?></dd>
    </dl>
    <?php if (in_array($item['status'] ?? '', ['draft', 'requested'], true)): ?>
    <form method="post" action="<?= e(url('/app/expenses/' . ($item['id'] ?? ''))) ?>" onsubmit="return confirm('Cancel this expense?');">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="btn btn-danger">Cancel expense</button>
    </form>
    <?php endif; ?>
</div>
