<div class="page-header">
    <h1>Documents</h1>
    <a class="btn btn-primary" href="<?= e(url('/app/documents/upload')) ?>">Upload</a>
</div>
<table class="table">
    <thead><tr><th>Title</th><th>Category</th><th>Uploaded</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($documents as $doc): ?>
        <tr>
            <td><?= e($doc['title'] ?? '') ?></td>
            <td><?= e($doc['category'] ?? '') ?></td>
            <td><?= e(sa_date($doc['created_at'] ?? '')) ?></td>
            <td><a href="<?= e(url('/app/documents/' . ($doc['id'] ?? '') . '/download')) ?>">Download</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
