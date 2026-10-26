<?= view('partials/page-header', [
    'title' => 'Contributions',
    'description' => 'Capture payments, review history, and manage arrears. Figures use recorded obligations and payments.',
], null) ?>
<?php if (!empty($metrics)): ?>
<section class="stat-grid" aria-label="This month contribution metrics" style="margin-bottom:1rem;">
    <div class="stat-card"><span class="stat-label">Expected this month</span><strong class="money"><?= e(money($metrics['expected'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Collected this month</span><strong class="money"><?= e(money($metrics['collected'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Outstanding this month</span><strong class="money"><?= e(money($metrics['outstanding'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Collection %</span><strong><?= e(number_format((float) ($metrics['collection_pct'] ?? 0), 1)) ?>%</strong></div>
    <div class="stat-card"><span class="stat-label">Members fully paid</span><strong><?= (int) ($metrics['fully_paid'] ?? 0) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Members in arrears</span><strong><a href="<?= e(url('/app/contributions/arrears')) ?>"><?= (int) ($metrics['in_arrears'] ?? 0) ?></a></strong></div>
    <div class="stat-card"><span class="stat-label">Admin fees collected (MTD)</span><strong class="money"><?= e(money($metrics['admin_fees'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span class="stat-label">Overdue contributions</span><strong class="money"><?= e(money($metrics['overdue'] ?? 0)) ?></strong></div>
</section>
<?php endif; ?>
<div class="page-actions" style="margin:-0.5rem 0 1rem;">
    <a class="btn btn-primary" href="<?= e(url('/app/contributions/create')) ?>">Record payment</a>
    <a class="btn btn-secondary" href="<?= e(url('/app/contributions/bulk')) ?>">Bulk capture</a>
    <a class="btn btn-secondary" href="<?= e(url('/app/contributions/import')) ?>">CSV import</a>
    <a class="btn btn-secondary" href="<?= e(url('/app/contributions/arrears')) ?>">Arrears</a>
</div>
<form method="get" action="<?= e(url('/app/contributions')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Member name">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All</option>
            <?php foreach ($statusOptions ?? ['completed', 'pending', 'failed', 'reversed'] as $st): ?>
                <option value="<?= e($st) ?>"<?= ($filterStatus ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterQ ?? '') !== '' || ($filterStatus ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/contributions')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<?php $list = isset($paginator) ? $paginator->items : ($rows ?? []); ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No contributions match', 'emptyMessage' => 'Try clearing filters, or record a payment.', 'emptyActionUrl' => url('/app/contributions/create'), 'emptyActionLabel' => 'Record payment'], null) ?>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead><tr><th>Member</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($list as $row): ?>
        <tr>
            <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
            <td class="money"><?= e(money($row['amount'] ?? 0)) ?></td>
            <td><?= e($row['payment_method'] ?? '') ?></td>
            <td><?= e(sa_date($row['paid_at'] ?? '')) ?></td>
            <td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php if (isset($paginator)) { echo view('partials/pagination', ['paginator' => $paginator], null); } ?>
<?php endif; ?>
