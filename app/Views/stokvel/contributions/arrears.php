<h1>Member arrears</h1>
<table class="table">
    <thead><tr><th>Member</th><th>Balance due</th><th>Period</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
            <td><?= e(money($row['balance_due'] ?? 0)) ?></td>
            <td><?= e($row['period_label'] ?? $row['period'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
