<?= view('partials/page-header', [
    'title' => 'Update document',
    'description' => 'Update the title or replace your uploaded file.',
], null) ?>
<?php $doc = $document ?? []; ?>
<form method="post" action="<?= e(url('/member/documents/' . (int) ($doc['id'] ?? 0))) ?>" class="form-stack card" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label" for="title">Title</label>
        <input id="title" name="title" required value="<?= e(old('title', $doc['title'] ?? '')) ?>">
    </div>
    <?php if (!empty($uploadsEnabled)): ?>
    <div class="form-group">
        <label class="form-label" for="upload_file">Replace file (optional)</label>
        <input type="file" id="upload_file" name="upload_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.txt,.csv">
        <p class="form-help">Leave blank to keep the current file.</p>
    </div>
    <?php endif; ?>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save changes</button>
        <a class="btn btn-secondary" href="<?= e(url('/member/documents/' . (int) ($doc['id'] ?? 0))) ?>">Cancel</a>
    </div>
</form>
