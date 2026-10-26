<?= view('partials/page-header', [
    'title' => 'Reports',
    'description' => 'Tenant-scoped financial and membership reports with CSV export.',
], null) ?>
<?php
$reports = $reports ?? [
    'members' => 'Member register',
    'contributions' => 'Contribution summary',
    'arrears' => 'Outstanding contributions',
    'expenses' => 'Expense report',
    'loans' => 'Loan portfolio',
    'payouts' => 'Payout schedule',
    'ledger' => 'Transaction ledger',
    'meetings' => 'Meeting attendance',
];
?>
<div class="grid grid-2 mb-4">
    <a class="card" href="<?= e(url('/app/reports/trends')) ?>" style="text-decoration:none;color:inherit;">
        <h2 class="card-title">Trends &amp; forecasting</h2>
        <p class="card-subtitle">Historical charts + labelled projections</p>
    </a>
    <a class="card" href="<?= e(url('/app/reports/annual')) ?>" style="text-decoration:none;color:inherit;">
        <h2 class="card-title">Annual management summary</h2>
        <p class="card-subtitle">Financial year totals (not audited)</p>
    </a>
</div>
<div class="grid grid-2">
<?php foreach ($reports as $key => $label): ?>
    <a class="card" href="<?= e(url('/app/reports/' . $key)) ?>" style="text-decoration:none;color:inherit;">
        <h2 class="card-title"><?= e(is_string($label) ? $label : ($label['title'] ?? $key)) ?></h2>
        <p class="card-subtitle">View and export</p>
    </a>
<?php endforeach; ?>
</div>
