<?php
$periodKey = $periodKey ?? '90';
$from = $periodFrom ?? '';
$to = $periodTo ?? '';
$qs = static function (array $extra = []) use ($periodKey, $from, $to): string {
    $base = array_merge([
        'period' => $periodKey,
        'from' => $periodKey === 'custom' ? $from : null,
        'to' => $periodKey === 'custom' ? $to : null,
    ], $extra);
    return http_build_query(array_filter($base, static fn ($v) => $v !== null && $v !== ''));
};
?>
<?= view('partials/page-header', [
    'title' => 'Member statement',
    'description' => 'Management statement of recorded charges and payments. This is not an audited financial statement.',
], null) ?>

<section class="card" aria-label="Statement filters">
    <form method="get" action="<?= e(url('/member/statement')) ?>" class="filter-bar">
        <div class="form-group">
            <label for="period">Period</label>
            <select id="period" name="period">
                <option value="30"<?= $periodKey === '30' ? ' selected' : '' ?>>Last 30 days</option>
                <option value="60"<?= $periodKey === '60' ? ' selected' : '' ?>>Last 60 days</option>
                <option value="90"<?= $periodKey === '90' ? ' selected' : '' ?>>Last 90 days</option>
                <option value="fy"<?= $periodKey === 'fy' ? ' selected' : '' ?>>Financial year</option>
                <option value="custom"<?= $periodKey === 'custom' ? ' selected' : '' ?>>Custom range</option>
            </select>
        </div>
        <div class="form-group">
            <label for="from">From</label>
            <input type="date" id="from" name="from" value="<?= e($from) ?>">
        </div>
        <div class="form-group">
            <label for="to">To</label>
            <input type="date" id="to" name="to" value="<?= e($to) ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Apply</button>
            <a class="btn btn-secondary no-print" href="<?= e(url('/member/statement?' . $qs(['format' => 'csv']))) ?>">Download CSV</a>
            <a class="btn btn-secondary no-print" href="<?= e(url('/member/statement?' . $qs(['print' => '1']))) ?>">Print / PDF</a>
        </div>
    </form>
</section>

<section class="card" style="margin-top:1rem;" aria-label="Statement header">
    <dl class="meta-grid">
        <div><dt>Stokvel</dt><dd><?= e($stokvel['name'] ?? '') ?></dd></div>
        <div><dt>Member</dt><dd><?= e($memberName ?? '') ?></dd></div>
        <div><dt>Period</dt><dd><?= e(sa_date($from)) ?> – <?= e(sa_date($to)) ?></dd></div>
        <div><dt>Opening balance</dt><dd class="money"><?= e(money($openingBalance ?? 0)) ?> <?= ((float) ($openingBalance ?? 0)) >= 0 ? '(owing)' : '(credit)' ?></dd></div>
        <div><dt>Closing balance</dt><dd class="money"><?= e(money($closingBalance ?? 0)) ?> <?= ((float) ($closingBalance ?? 0)) >= 0 ? '(owing)' : '(credit)' ?></dd></div>
    </dl>
    <p class="muted" style="margin-top:0.75rem;">Positive balances mean amounts recorded as owing to the stokvel. Payments are recorded confirmations, not bank settlements.</p>
</section>

<?php if (empty($lines)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No transactions in this period',
        'emptyMessage' => 'Try a wider date range.',
    ], null) ?>
<?php else: ?>
<div class="table-wrap" style="margin-top:1rem;">
    <table class="table">
        <thead>
            <tr>
                <th scope="col">Date</th>
                <th scope="col">Reference</th>
                <th scope="col">Description</th>
                <th scope="col">Debit</th>
                <th scope="col">Credit</th>
                <th scope="col">Running balance</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lines as $line): ?>
            <tr>
                <td><?= e(sa_date($line['entry_date'] ?? '')) ?></td>
                <td><?= e((string) ($line['reference'] ?? '')) ?></td>
                <td><?= e((string) ($line['description'] ?? '')) ?></td>
                <td class="money"><?= ((float) ($line['debit'] ?? 0)) > 0 ? e(money($line['debit'])) : '—' ?></td>
                <td class="money"><?= ((float) ($line['credit'] ?? 0)) > 0 ? e(money($line['credit'])) : '—' ?></td>
                <td class="money"><?= e(money($line['balance'] ?? 0)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
