<div class="card">
    <h1>New Meeting</h1>
    <form method="post" action="<?= e(url('/app/meetings')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= e((string) old('title', '')) ?>" required>
        </div>
        <div class="form-group">
            <label for="scheduled_at">Date & time</label>
            <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="<?= e((string) old('scheduled_at', '')) ?>" required>
        </div>
        <div class="form-group">
            <label for="meeting_type">Meeting type</label>
            <select id="meeting_type" name="meeting_type">
                <?php if (!empty($meetingCategories)): ?>
                    <?php foreach ($meetingCategories as $cat): ?>
                        <option value="<?= e($cat['code'] ?? '') ?>"<?= old('meeting_type', 'regular') === ($cat['code'] ?? '') ? ' selected' : '' ?>>
                            <?= e($cat['name'] ?? $cat['code'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach (($meetingTypes ?? ['regular', 'agm', 'special', 'committee']) as $type): ?>
                        <option value="<?= e($type) ?>"<?= old('meeting_type', 'regular') === $type ? ' selected' : '' ?>>
                            <?= e(strtoupper($type) === 'AGM' ? 'AGM' : ucfirst($type)) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <p class="help-text"><a href="<?= e(url('/app/meetings/categories')) ?>">Manage meeting categories</a></p>
        </div>
        <div class="form-group">
            <label for="venue">Venue</label>
            <input type="text" id="venue" name="venue" value="<?= e((string) old('venue', '')) ?>">
        </div>
        <div class="form-group">
            <label for="agenda">Agenda</label>
            <textarea id="agenda" name="agenda" rows="6" data-wysiwyg class="wysiwyg" aria-label="Agenda"><?= e((string) old('agenda', '')) ?></textarea>
        </div>
        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="6" data-wysiwyg class="wysiwyg" data-wysiwyg-size="tall" aria-label="Notes"><?= e((string) old('notes', '')) ?></textarea>
            <p class="help-text">Rich text (no images or file attachments). Unsafe markup is stripped on save.</p>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>
