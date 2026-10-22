<div class="page-header no-print">
    <h1><?= e($reportTitle ?? 'Report') ?></h1>
    <form method="get" class="toolbar">
        <input type="date" name="from" value="<?= e($from ?? '') ?>">
        <input type="date" name="to" value="<?= e($to ?? '') ?>">
        <button type="submit" class="btn">Apply</button>
        <a class="btn" href="<?= e(url('/app/reports/' . ($type ?? '') . '?from=' . urlencode($from ?? '') . '&to=' . urlencode($to ?? '') . '&print=1')) ?>">Print</a>
        <a class="btn btn-primary" href="<?= e(url('/app/reports/' . rawurlencode((string) ($type ?? '')) . '/export?from=' . urlencode((string) ($from ?? '')) . '&to=' . urlencode((string) ($to ?? '')))) ?>">Export CSV</a>
    </form>
</div>
<?php if (empty($rows)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No data for this period'], null) ?>
<?php else: ?>
<table class="table">
    <thead>
        <tr>
            <?php foreach (array_keys($rows[0]) as $col): ?>
                <th><?= e((string) $col) ?></th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <?php foreach ($row as $cell): ?>
                <td><?= e(is_scalar($cell) || $cell === null ? (string) ($cell ?? '') : json_encode($cell)) ?></td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
