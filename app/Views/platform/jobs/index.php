<table class="table">
    <thead><tr><th>Channel</th><th>Recipient</th><th>Status</th><th>Attempts</th><th>Updated</th><th>Error</th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e($row['channel'] ?? '') ?></td>
            <td><?= e($row['recipient'] ?? '') ?></td>
            <td><?= e($row['status'] ?? '') ?></td>
            <td><?= e((string) (($row['attempt_count'] ?? 0) . '/' . ($row['max_attempts'] ?? 0))) ?></td>
            <td><?= e(sa_datetime($row['updated_at'] ?? '')) ?></td>
            <td><?= e(mb_substr((string) ($row['last_error'] ?? ''), 0, 120)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
