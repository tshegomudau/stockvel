<table class="table">
    <thead><tr><th>Title</th><th>Category</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($documents as $doc): ?>
        <tr>
            <td><?= e($doc['title'] ?? '') ?></td>
            <td><?= e($doc['category'] ?? '') ?></td>
            <td><a href="<?= e(url('/member/documents/' . ($doc['id'] ?? '') . '/download')) ?>">Download</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
