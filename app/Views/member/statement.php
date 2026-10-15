<h1>My statement</h1>
<table class="table">
    <thead><tr><th>Date</th><th>Description</th><th>Amount</th></tr></thead>
    <tbody>
    <?php foreach ($lines as $line): ?>
        <tr>
            <td><?= e(sa_date($line['entry_date'] ?? '')) ?></td>
            <td><?= e($line['description'] ?? '') ?></td>
            <td><?= e(money($line['amount'] ?? 0)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="no-print"><a class="btn" href="<?= e(url('/member/statement?print=1')) ?>">Print-friendly view</a></p>
