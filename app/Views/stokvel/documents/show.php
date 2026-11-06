<?= view('partials/page-header', [
    'title' => $document['title'] ?? 'Document',
    'description' => ucfirst((string) ($document['category'] ?? 'general')) . ' · ' . sa_datetime($document['updated_at'] ?? $document['created_at'] ?? ''),
], null) ?>
<p class="mb-4 page-actions">
    <?php if (can('documents.upload')): ?>
        <a class="btn btn-secondary" href="<?= e(url('/app/documents/' . (int) $document['id'] . '/edit')) ?>">Edit</a>
        <form method="post" action="<?= e(url('/app/documents/' . (int) $document['id'] . '/delete')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger" data-confirm="Delete this document? It will no longer be available to members.">Delete</button>
        </form>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= e(url('/app/documents')) ?>">Back to list</a>
</p>
<article class="card prose document-body">
    <?php if (($document['content_type'] ?? '') === 'typed'): ?>
        <?= ($document['body_html'] ?? '') !== '' ? safe_html((string) $document['body_html']) : '<p class="text-muted">No content.</p>' ?>
    <?php else: ?>
        <p>This is an uploaded file.</p>
        <a class="btn btn-primary" href="<?= e(url('/app/documents/' . (int) $document['id'] . '/download')) ?>">Download</a>
    <?php endif; ?>
</article>
