<?= view('partials/page-header', [
    'title' => 'Documents',
    'description' => 'Constitutions, policies, and other published documents from your stokvel.',
], null) ?>

<?php if (($documents ?? []) === []): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No documents',
        'emptyMessage' => 'Published documents will appear here.',
    ], null) ?>
<?php else: ?>
<table class="table">
    <thead><tr><th>Title</th><th>Category</th><th>Type</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($documents as $doc): ?>
        <tr>
            <td>
                <a href="<?= e(url('/member/documents/' . (int) ($doc['id'] ?? 0))) ?>">
                    <?= e($doc['title'] ?? '') ?>
                </a>
            </td>
            <td><?= e($doc['category_name'] ?? $doc['category'] ?? '') ?></td>
            <td><?= (($doc['content_type'] ?? '') === 'typed') ? 'Typed' : 'File' ?></td>
            <td>
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/member/documents/' . (int) ($doc['id'] ?? 0))) ?>">View</a>
                <?php if (($doc['content_type'] ?? '') !== 'typed'): ?>
                    <a class="btn btn-sm" href="<?= e(url('/member/documents/' . (int) ($doc['id'] ?? 0) . '/download')) ?>">Download</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
