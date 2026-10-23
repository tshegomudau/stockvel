<?= view('partials/page-header', [
    'title' => 'Member arrears',
    'description' => 'Outstanding contribution balances. Capture historical arrears for stokvels that kept records manually.',
], null) ?>
<?php if (!empty($canCapture)): ?>
<div class="page-actions mb-4">
    <a class="btn btn-primary" href="<?= e(url('/app/contributions/arrears/capture')) ?>">Capture arrears</a>
    <a class="btn btn-secondary" href="<?= e(url('/app/contributions/arrears/import')) ?>">Bulk import</a>
</div>
<?php endif; ?>

<?php if (empty($rows)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No arrears',
        'emptyMessage' => 'No outstanding contribution balances.',
        'emptyActionUrl' => !empty($canCapture) ? url('/app/contributions/arrears/capture') : null,
        'emptyActionLabel' => 'Capture arrears',
    ], null) ?>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Member</th><th>Balance due</th><th>Unpaid charges</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <?php
        $owing = (int) ($row['owing_count'] ?? 0);
        $rowClass = $owing > 2 ? 'row-owing-high' : ($owing >= 1 ? 'row-owing-low' : '');
        ?>
        <tr class="<?= e($rowClass) ?>">
            <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
            <td class="money"><?= e(money($row['balance_due'] ?? 0)) ?></td>
            <td><?= e((string) $owing) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
