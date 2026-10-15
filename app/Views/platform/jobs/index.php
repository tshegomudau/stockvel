<table class="table">
    <thead><tr><th>Queue</th><th>Job</th><th>Failed</th><th>Attempts</th><th>Message</th></tr></thead>
    <tbody>
    <?php foreach ($paginator->items as $row): ?>
        <tr>
            <td><?= e($row['queue'] ?? '') ?></td>
            <td><?= e($row['job_class'] ?? '') ?></td>
            <td><?= e(sa_datetime($row['failed_at'] ?? '')) ?></td>
            <td><?= e((string) ($row['attempts'] ?? '')) ?></td>
            <td><?= e(mb_substr((string) ($row['exception_message'] ?? ''), 0, 120)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
