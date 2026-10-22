<?php $item = $item ?? []; ?>
<div class="page-header">
    <h1><?= e($item['name'] ?? ('Payout #' . ($item['id'] ?? ''))) ?></h1>
    <a class="btn" href="<?= e(url('/app/payouts/' . ($item['id'] ?? '') . '/edit')) ?>">Edit</a>
</div>
<div class="card">
    <dl class="detail-list">
        <dt>Cycle code</dt><dd><?= e($item['cycle_code'] ?? '—') ?></dd>
        <dt>Category</dt><dd><?= e($item['category_name'] ?? '—') ?></dd>
        <dt>Status</dt><dd><span class="badge"><?= e($item['status'] ?? '') ?></span></dd>
        <dt>Start date</dt><dd><?= e(sa_date($item['start_date'] ?? '')) ?></dd>
        <dt>End date</dt><dd><?= e(sa_date($item['end_date'] ?? '')) ?></dd>
        <dt>Payout amount</dt><dd class="money"><?= e(money($item['payout_amount'] ?? 0)) ?></dd>
    </dl>
</div>

<?php if (!empty($schedule)): ?>
<section class="card mt-6">
    <h2 class="card-title">Schedule</h2>
    <div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>Member</th><th>Amount</th><th>Scheduled</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($schedule as $row): ?>
    <tr>
        <td><?= e((string) ($row['sequence_number'] ?? '')) ?></td>
        <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
        <td class="money"><?= e(money($row['amount'] ?? 0)) ?></td>
        <td><?= e(sa_date($row['scheduled_date'] ?? '')) ?></td>
        <td><span class="badge"><?= e($row['status'] ?? '') ?></span></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php endif; ?>

<div class="mt-4">
    <form method="post" action="<?= e(url('/app/payouts/' . ($item['id'] ?? ''))) ?>" onsubmit="return confirm('Cancel this payout cycle?');">
        <?= csrf_field() ?>
        <?= method_field('DELETE') ?>
        <button type="submit" class="btn btn-danger">Cancel cycle</button>
    </form>
</div>
