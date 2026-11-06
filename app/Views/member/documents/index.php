<?= view('partials/page-header', [
    'title' => 'Documents',
    'description' => 'Published documents from your stokvel, plus any files you uploaded.',
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
        <?php $owns = (($doc['content_type'] ?? '') !== 'typed') && (int) ($doc['uploaded_by_user_id'] ?? 0) === (int) ($currentUserId ?? 0); ?>
        <tr>
            <td>
                <a href="<?= e(url('/member/documents/' . (int) ($doc['id'] ?? 0))) ?>">
                    <?= e($doc['title'] ?? '') ?>
                </a>
            </td>
            <td><?= e($doc['category_name'] ?? $doc['category'] ?? '') ?></td>
            <td><?= (($doc['content_type'] ?? '') === 'typed') ? 'Typed' : 'File' ?></td>
            <td class="actions">
                <a class="btn btn-sm btn-secondary" href="<?= e(url('/member/documents/' . (int) ($doc['id'] ?? 0))) ?>">View</a>
                <?php if (($doc['content_type'] ?? '') !== 'typed'): ?>
                    <a class="btn btn-sm" href="<?= e(url('/member/documents/' . (int) ($doc['id'] ?? 0) . '/download')) ?>">Download</a>
                <?php endif; ?>
                <?php if ($owns): ?>
                    <a class="btn btn-sm btn-secondary" href="<?= e(url('/member/documents/' . (int) ($doc['id'] ?? 0) . '/edit')) ?>">Edit</a>
                    <form method="post" action="<?= e(url('/member/documents/' . (int) ($doc['id'] ?? 0) . '/delete')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Delete this uploaded document?">Delete</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
