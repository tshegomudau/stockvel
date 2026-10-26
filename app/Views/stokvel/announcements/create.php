<?= view('partials/page-header', [
    'title' => 'New announcement',
    'description' => 'Saved as a draft. Publish when ready for members to see.',
], null) ?>
<form method="post" action="<?= e(url('/app/announcements')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label" for="title">Title</label>
        <input id="title" name="title" required value="<?= e(old('title', '')) ?>">
    </div>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="priority">Priority</label>
            <select id="priority" name="priority">
                <?php foreach (['low', 'normal', 'high', 'urgent'] as $p): ?>
                    <option value="<?= e($p) ?>"<?= old('priority', 'normal') === $p ? ' selected' : '' ?>><?= e(ucfirst($p)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="audience">Audience</label>
            <select id="audience" name="audience">
                <?php foreach (['all' => 'Everyone', 'members' => 'Members', 'admins' => 'Admins'] as $val => $label): ?>
                    <option value="<?= e($val) ?>"<?= old('audience', 'all') === $val ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="body_html">Body</label>
        <textarea id="body_html" name="body_html" rows="12" class="wysiwyg wysiwyg--tall" data-wysiwyg data-wysiwyg-size="tall" aria-label="Announcement body"><?= e(old('body_html', '')) ?></textarea>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save draft</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/announcements')) ?>">Cancel</a>
    </div>
</form>
