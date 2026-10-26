<?= view('partials/page-header', [
    'title' => 'Trends & forecasting',
    'description' => 'Figures from recorded payments, obligations, expenses, and attendance. Forecasts are labelled projections only.',
], null) ?>
<?php $series = $series ?? ['months' => [], 'forecast' => [], 'disclaimer' => '']; ?>
<p class="muted mb-4"><?= e((string) ($series['disclaimer'] ?? '')) ?></p>
<p class="mb-4"><a href="<?= e(url('/app/reports')) ?>">← Reports</a> · <a href="<?= e(url('/app/reports/annual')) ?>">Annual summary</a></p>

<section class="card mb-4" aria-label="Historical months">
    <h2 class="h3">Last <?= count($series['months'] ?? []) ?> months (recorded)</h2>
    <?php if (empty($series['months'])): ?>
        <p class="text-muted">No historical data yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Month</th>
                    <th scope="col">Expected</th>
                    <th scope="col">Collected</th>
                    <th scope="col">Arrears (as of month-end)</th>
                    <th scope="col">Expenses</th>
                    <th scope="col">Fines collected</th>
                    <th scope="col">New members</th>
                    <th scope="col">Attendance</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($series['months'] as $row): ?>
                <tr>
                    <td><?= e($row['label'] ?? '') ?></td>
                    <td class="money"><?= e(money($row['expected'] ?? 0)) ?></td>
                    <td class="money"><?= e(money($row['collected'] ?? 0)) ?></td>
                    <td class="money"><?= e(money($row['arrears'] ?? 0)) ?></td>
                    <td class="money"><?= e(money($row['expenses'] ?? 0)) ?></td>
                    <td class="money"><?= e(money($row['fines_collected'] ?? 0)) ?></td>
                    <td><?= (int) ($row['members_joined'] ?? 0) ?></td>
                    <td><?= $row['attendance_pct'] !== null ? e(number_format((float) $row['attendance_pct'], 1) . '%') : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    $labels = array_map(static fn ($r) => $r['label'] ?? '', $series['months']);
    $collected = array_map(static fn ($r) => (float) ($r['collected'] ?? 0), $series['months']);
    $expected = array_map(static fn ($r) => (float) ($r['expected'] ?? 0), $series['months']);
    ?>
    <?php if (array_sum($collected) > 0 || array_sum($expected) > 0): ?>
        <div class="chart-canvas mt-4" data-chart
             data-labels='<?= e(json_encode(array_values($labels))) ?>'
             data-values='<?= e(json_encode(array_values($collected))) ?>'
             data-color="#14532d"
             aria-label="Collected contributions chart"></div>
        <p class="text-sm text-muted">Chart: collected contributions by month (actual recorded payments).</p>
    <?php endif; ?>
    <?php endif; ?>
</section>

<section class="card" aria-label="Forecast">
    <h2 class="h3">Projected next 3 months</h2>
    <p class="badge">Forecast</p>
    <?php if (empty($series['forecast'])): ?>
        <p class="text-muted">Not enough history to project.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Month</th>
                    <th scope="col">Projected collections</th>
                    <th scope="col">Method</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($series['forecast'] as $row): ?>
                <tr>
                    <td><?= e($row['label'] ?? '') ?> <span class="badge">Forecast</span></td>
                    <td class="money"><?= e(money($row['projected_collected'] ?? 0)) ?></td>
                    <td class="text-sm"><?= e($row['method'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
