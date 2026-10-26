<?= view('partials/page-header', [
    'title' => 'Annual management summary',
    'description' => 'Management report from application records — not an audited financial statement.',
], null) ?>
<?php $summary = $summary ?? []; ?>
<form method="get" action="<?= e(url('/app/reports/annual')) ?>" class="filter-bar no-print mb-4">
    <div class="form-group">
        <label for="from">From</label>
        <input type="date" id="from" name="from" value="<?= e($from ?? '') ?>">
    </div>
    <div class="form-group">
        <label for="to">To</label>
        <input type="date" id="to" name="to" value="<?= e($to ?? '') ?>">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Apply</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/reports/annual?from=' . rawurlencode((string) ($from ?? '')) . '&to=' . rawurlencode((string) ($to ?? '')) . '&print=1')) ?>">Print / PDF</a>
        <a class="btn btn-ghost" href="<?= e(url('/app/reports')) ?>">Back</a>
    </div>
</form>

<p class="muted"><?= e((string) ($summary['disclaimer'] ?? '')) ?></p>
<p>Period: <?= e(sa_date($summary['from'] ?? '')) ?> – <?= e(sa_date($summary['to'] ?? '')) ?></p>

<section class="stat-grid mt-4" aria-label="Annual totals">
    <div class="stat-card"><span class="stat-label">Total contributions</span><strong class="money"><?= e(money($summary['total_contributions'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Total admin fees</span><strong class="money"><?= e(money($summary['total_admin_fees'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Total expenses</span><strong class="money"><?= e(money($summary['total_expenses'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Outstanding contributions</span><strong class="money"><?= e(money($summary['outstanding_contributions'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Loans issued</span><strong class="money"><?= e(money($summary['loans_issued'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Loans outstanding</span><strong class="money"><?= e(money($summary['loans_outstanding'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Repayments</span><strong class="money"><?= e(money($summary['repayments'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Payouts</span><strong class="money"><?= e(money($summary['payouts'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Fines issued</span><strong class="money"><?= e(money($summary['fines'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Active members</span><strong><?= (int) ($summary['member_count'] ?? 0) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Meetings held</span><strong><?= (int) ($summary['meetings_held'] ?? 0) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Average attendance</span><strong><?= ($summary['average_attendance'] ?? null) !== null ? e(number_format((float) $summary['average_attendance'], 1) . '%') : '—' ?></strong></div>
</section>
