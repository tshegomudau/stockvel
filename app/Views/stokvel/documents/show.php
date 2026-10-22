<?= view('partials/page-header', [
    'title' => $document['title'] ?? 'Document',
    'description' => ucfirst((string) ($document['category'] ?? 'general')) . ' · ' . sa_datetime($document['updated_at'] ?? $document['created_at'] ?? ''),
    'actionUrl' => (($document['content_type'] ?? '') === 'typed') ? url('/app/documents/' . (int) $document['id'] . '/edit') : null,
    'actionLabel' => 'Edit',
], null) ?>
<article class="card prose document-body">
    <?php if (($document['content_type'] ?? '') === 'typed'): ?>
        <?= ($document['body_html'] ?? '') !== '' ? safe_html((string) $document['body_html']) : '<p class="text-muted">No content.</p>' ?>
    <?php else: ?>
        <p>This is an uploaded file. Downloads are a Phase 2 paid feature.</p>
        <a class="btn btn-secondary" href="<?= e(url('/app/documents/' . (int) $document['id'] . '/download')) ?>">Try download</a>
    <?php endif; ?>
</article>
