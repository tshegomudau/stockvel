<?= view('partials/page-header', [
    'title' => $cycle['title'] ?? 'Grocery cycle',
    'description' => 'Status: ' . ($cycle['status'] ?? '') . (!empty($cycle['distribution_date']) ? ' · Distribute ' . sa_date($cycle['distribution_date']) : ''),
], null) ?>
<section class="card mb-6">
    <dl class="detail-list">
        <dt>Reference</dt><dd><?= e($cycle['cycle_ref'] ?? '') ?></dd>
        <dt>Target</dt><dd><?= e(($cycle['target_amount'] ?? null) !== null ? money($cycle['target_amount']) : '—') ?></dd>
        <dt>Notes</dt><dd><?= e($cycle['notes'] ?? '—') ?></dd>
    </dl>
</section>
<section class="card">
    <h2 class="card-title">Items</h2>
    <?php if (empty($items)): ?>
        <p class="text-muted">No line items yet.</p>
    <?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Est. cost</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['item_name'] ?? '') ?></td>
                    <td><?= e((string) ($item['quantity'] ?? '')) ?></td>
                    <td><?= e($item['unit'] ?? '—') ?></td>
                    <td><?= e(($item['estimated_cost'] ?? null) !== null ? money($item['estimated_cost']) : '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
