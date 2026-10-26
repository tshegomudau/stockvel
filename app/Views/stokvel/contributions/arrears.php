<?= view('partials/page-header', [
    'title' => 'Member arrears',
    'description' => 'Outstanding contribution balances from recorded obligations. Capture historical arrears where records were kept manually.',
], null) ?>
<?php if (!empty($canCapture)): ?>
<div class="page-actions mb-4">
    <a class="btn btn-primary" href="<?= e(url('/app/contributions/arrears/capture')) ?>">Capture arrears</a>
    <a class="btn btn-secondary" href="<?= e(url('/app/contributions/arrears/import')) ?>">Bulk import</a>
</div>
<?php endif; ?>

<nav class="filter-bar mb-4" aria-label="Arrears ageing">
    <?php
    $ageing = $ageing ?? '';
    $links = ['' => 'All', '30' => '30+ days', '60' => '60+ days', '90' => '90+ days'];
    foreach ($links as $key => $label):
        $href = $key === '' ? url('/app/contributions/arrears') : url('/app/contributions/arrears?ageing=' . $key);
        $active = (string) $ageing === (string) $key;
    ?>
        <a class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-secondary' ?>" href="<?= e($href) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (empty($rows)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No arrears',
        'emptyMessage' => 'No outstanding contribution balances for this filter.',
        'emptyActionUrl' => !empty($canCapture) ? url('/app/contributions/arrears/capture') : null,
        'emptyActionLabel' => 'Capture arrears',
    ], null) ?>
<?php else: ?>
<div class="table-wrap">
<table class="table">
    <thead>
        <tr>
            <th scope="col">Member</th>
            <th scope="col">Outstanding</th>
            <th scope="col">Oldest unpaid</th>
            <th scope="col">Days overdue</th>
            <th scope="col">Last contribution</th>
            <th scope="col">Unpaid charges</th>
            <th scope="col">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <?php
        $owing = (int) ($row['owing_count'] ?? 0);
        $days = (int) ($row['days_overdue'] ?? 0);
        $rowClass = $days >= 90 ? 'row-owing-high' : ($owing >= 1 ? 'row-owing-low' : '');
        $memberId = (int) ($row['member_id'] ?? 0);
        ?>
        <tr class="<?= e($rowClass) ?>">
            <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
            <td class="money"><?= e(money($row['balance_due'] ?? 0)) ?></td>
            <td><?= e(sa_date($row['oldest_due'] ?? '')) ?></td>
            <td><?= $days ?> days</td>
            <td><?= e(sa_date($row['last_contribution'] ?? '')) ?: '—' ?></td>
            <td><?= e((string) $owing) ?></td>
            <td class="no-print">
                <a href="<?= e(url('/app/members/' . $memberId)) ?>">View member</a>
                ·
                <a href="<?= e(url('/member/statement?membership_id=' . $memberId . '&period=90')) ?>">Statement</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
