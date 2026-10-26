<?= view('partials/page-header', [
    'title' => 'Edit constitution draft',
    'description' => 'Draft version ' . (int) ($draft['version_number'] ?? 0) . '. Publish when the committee has approved the text.',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/constitution')) ?>">&larr; Current constitution</a></p>

<form method="post" action="<?= e(url('/app/constitution/edit')) ?>" class="form-stack card mb-6">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label" for="title">Title</label>
        <input id="title" name="title" value="<?= e(old('title', $constitution['title'] ?? 'Stokvel Constitution')) ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="body_html">Body</label>
        <textarea id="body_html" name="body_html" rows="18" class="wysiwyg wysiwyg--tall" data-wysiwyg data-wysiwyg-size="tall" aria-label="Constitution body"><?= e(old('body_html', $draft['body_html'] ?? '')) ?></textarea>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save draft</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/constitution')) ?>">Cancel</a>
    </div>
</form>

<form method="post" action="<?= e(url('/app/constitution/publish')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <h2>Publish this draft</h2>
    <div class="form-group">
        <label class="form-label" for="effective_from">Effective from</label>
        <input id="effective_from" name="effective_from" type="date" value="<?= e(date('Y-m-d')) ?>" required>
    </div>
    <button type="submit" class="btn btn-primary" data-confirm="Publish this constitution version? It becomes the current official document.">Publish version</button>
</form>
