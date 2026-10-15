<h1>Statement — <?= e(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?></h1>
<table class="table">
    <thead><tr><th>Date</th><th>Description</th><th>Amount</th></tr></thead>
    <tbody>
    <?php foreach ($lines as $line): ?>
        <tr>
            <td><?= e(sa_date($line['entry_date'] ?? '')) ?></td>
            <td><?= e($line['description'] ?? $line['type'] ?? '') ?></td>
            <td><?= e(money($line['amount'] ?? 0)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
