<?= view('partials/page-header', [
    'title' => $document['title'] ?? 'Document',
    'description' => ucfirst((string) ($document['category_name'] ?? $document['category'] ?? 'general'))
        . ' · ' . sa_datetime($document['updated_at'] ?? $document['created_at'] ?? ''),
], null) ?>

<p class="mb-4 page-actions">
    <a href="<?= e(url('/member/documents')) ?>">← All documents</a>
    <?php if (!empty($document['meeting_id'])): ?>
        · <a href="<?= e(url('/member/meetings/' . (int) $document['meeting_id'])) ?>">Related meeting</a>
    <?php endif; ?>
    <?php if (!empty($canManage)): ?>
        <a class="btn btn-secondary" href="<?= e(url('/member/documents/' . (int) $document['id'] . '/edit')) ?>">Edit</a>
        <form method="post" action="<?= e(url('/member/documents/' . (int) $document['id'] . '/delete')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger" data-confirm="Delete this uploaded document?">Delete</button>
        </form>
    <?php endif; ?>
</p>

<article class="card prose document-body">
    <?php if (($document['content_type'] ?? '') === 'typed'): ?>
        <?= ($document['body_html'] ?? '') !== '' ? safe_html((string) $document['body_html']) : '<p class="text-muted">No content.</p>' ?>
    <?php else: ?>
        <p class="text-muted">This document is a file upload.</p>
        <a class="btn btn-primary" href="<?= e(url('/member/documents/' . (int) $document['id'] . '/download')) ?>">Download</a>
    <?php endif; ?>
</article>
