<?php
$doc = $document ?? [];
$cats = $categories ?? [];
$uploadsEnabled = !empty($uploadsEnabled);
$isEdit = !empty($doc['id']);
$isFile = $isEdit && (($doc['content_type'] ?? '') !== 'typed');
$isTypedEdit = $isEdit && !$isFile;
?>
<?= view('partials/page-header', [
    'title' => $isEdit ? 'Edit document' : 'New document',
    'description' => $uploadsEnabled
        ? 'Type constitution, minutes, or policies, or upload a file when storage is enabled.'
        : 'Type constitution, minutes, or policies here. File uploads can be enabled by the platform admin.',
], null) ?>
<form method="post" action="<?= e(url($isEdit ? '/app/documents/' . (int) $doc['id'] : '/app/documents')) ?>" class="form-stack card"<?= (!$isEdit && $uploadsEnabled) || $isFile ? ' enctype="multipart/form-data"' : '' ?>>
    <?= csrf_field() ?>
    <?php if (!$isEdit && $uploadsEnabled): ?>
    <div class="form-group">
        <span class="form-label">Content type</span>
        <label class="form-check"><input type="radio" name="content_mode" value="typed" checked data-doc-mode> Typed document</label>
        <label class="form-check"><input type="radio" name="content_mode" value="file" data-doc-mode> Uploaded file</label>
    </div>
    <?php elseif ($isFile): ?>
        <input type="hidden" name="content_mode" value="file">
        <p class="form-help">Uploaded file — update details below. Replace the file only if needed.</p>
    <?php else: ?>
        <input type="hidden" name="content_mode" value="typed">
    <?php endif; ?>
    <div class="form-group">
        <label class="form-label" for="title">Title</label>
        <input class="form-control" type="text" id="title" name="title" required value="<?= e(old('title', $doc['title'] ?? '')) ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="document_category_id">Category</label>
        <?php if ($cats === []): ?>
            <p class="form-help text-danger">No active document categories. <a href="<?= e(url('/app/documents/categories')) ?>">Add a category</a> first.</p>
        <?php else: ?>
        <select class="form-control form-select" id="document_category_id" name="document_category_id" required>
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
        <select class="form-control form-select" id="meeting_id" name="meeting_id">
            <option value="">— None —</option>
            <?php foreach (($meetings ?? []) as $mtg): ?>
                <option value="<?= (int) $mtg['id'] ?>"<?= (string) old('meeting_id', (string) ($doc['meeting_id'] ?? '')) === (string) $mtg['id'] ? ' selected' : '' ?>>
                    <?= e(($mtg['title'] ?? 'Meeting') . (!empty($mtg['scheduled_at']) ? ' · ' . sa_datetime($mtg['scheduled_at']) : '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="form-help">Linked documents appear on the member meeting page.</p>
    </div>
    <?php if (!$isFile): ?>
    <div class="form-group" data-doc-typed>
        <label class="form-label" for="body_html">Content</label>
        <textarea id="body_html" name="body_html" rows="16" class="wysiwyg wysiwyg--tall form-control form-textarea" data-wysiwyg data-wysiwyg-size="tall" aria-label="Document content"><?= e(old('body_html', $doc['body_html'] ?? '')) ?></textarea>
        <p class="form-help">Editor features follow platform settings (full toolbar, images, tables, colours).</p>
    </div>
    <?php endif; ?>
    <?php if ((!$isEdit && $uploadsEnabled) || ($isFile && $uploadsEnabled)): ?>
    <div class="form-group" data-doc-file<?= $isFile ? '' : ' hidden' ?>>
        <label class="form-label" for="upload_file"><?= $isFile ? 'Replace file (optional)' : 'File' ?></label>
        <input class="form-control" type="file" id="upload_file" name="upload_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.txt,.csv"<?= $isFile ? '' : '' ?>>
        <p class="form-help"><?= $isFile
            ? 'Leave blank to keep the current file. Replacing creates a new version.'
            : 'Stored using the platform storage driver. Uploaded files are private — only the uploader and admins with document access can see them. Use a typed document to publish content to all members.' ?></p>
    </div>
    <?php endif; ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Save document' ?></button>
        <a class="btn btn-secondary" href="<?= e(url($isEdit ? '/app/documents/' . (int) $doc['id'] : '/app/documents')) ?>">Cancel</a>
    </div>
</form>
<?php if (!$isEdit && $uploadsEnabled): ?>
<script>
(function () {
  var radios = document.querySelectorAll('[data-doc-mode]');
  if (!radios.length) return;
  var typed = document.querySelector('[data-doc-typed]');
  var file = document.querySelector('[data-doc-file]');
  function sync() {
    var mode = 'typed';
    radios.forEach(function (r) { if (r.checked) mode = r.value; });
    if (typed) typed.hidden = mode === 'file';
    if (file) file.hidden = mode !== 'file';
  }
  radios.forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
</script>
<?php endif; ?>
