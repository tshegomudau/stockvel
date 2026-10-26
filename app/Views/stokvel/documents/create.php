<?= view('partials/page-header', [
    'title' => $document['id'] ?? null ? 'Edit document' : 'New document',
    'description' => 'Type constitution, minutes, or policies here. File uploads are a Phase 2 paid feature.',
], null) ?>
<?php $doc = $document ?? []; ?>
<?php $cats = $categories ?? []; ?>
<form method="post" action="<?= e(url(!empty($doc['id']) ? '/app/documents/' . (int) $doc['id'] : '/app/documents')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label" for="title">Title</label>
        <input id="title" name="title" required value="<?= e(old('title', $doc['title'] ?? '')) ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="document_category_id">Category</label>
        <?php if ($cats === []): ?>
            <p class="form-help text-danger">No active document categories. <a href="<?= e(url('/app/documents/categories')) ?>">Add a category</a> first.</p>
        <?php else: ?>
        <select id="document_category_id" name="document_category_id" required>
            <option value="">Select category</option>
            <?php foreach ($cats as $cat): ?>
                <option value="<?= (int) $cat['id'] ?>"<?= (string) old('document_category_id', (string) ($doc['document_category_id'] ?? '')) === (string) $cat['id'] ? ' selected' : '' ?>>
                    <?= e($cat['name'] ?? '') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>
    <div class="form-group">
        <label class="form-label" for="meeting_id">Link to meeting (optional)</label>
        <select id="meeting_id" name="meeting_id">
            <option value="">— None —</option>
            <?php foreach (($meetings ?? []) as $mtg): ?>
                <option value="<?= (int) $mtg['id'] ?>"<?= (string) old('meeting_id', (string) ($doc['meeting_id'] ?? '')) === (string) $mtg['id'] ? ' selected' : '' ?>>
                    <?= e(($mtg['title'] ?? 'Meeting') . (!empty($mtg['scheduled_at']) ? ' · ' . sa_datetime($mtg['scheduled_at']) : '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="form-help">Linked documents appear on the member meeting page.</p>
    </div>
    <div class="form-group">
        <label class="form-label" for="body_html">Content</label>
        <textarea id="body_html" name="body_html" rows="16" class="wysiwyg wysiwyg--tall" data-wysiwyg data-wysiwyg-size="tall" aria-label="Document content"><?= e(old('body_html', $doc['body_html'] ?? '')) ?></textarea>
        <p class="form-help">Full text editor (headings, lists, links, quotes). Images and file attachments are not supported.</p>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save document</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/documents')) ?>">Cancel</a>
    </div>
</form>
