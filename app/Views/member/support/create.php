<?= view('partials/page-header', [
    'title' => 'Log a bug / support request',
    'description' => 'Describe what happened, what you expected, and how to reproduce.',
], null) ?>
<?php
use App\Core\Session;
$fieldErrors = Session::getFlash('errors', []);
if (!is_array($fieldErrors)) {
    $fieldErrors = [];
}
?>
<form method="post" action="<?= e(url('/member/support')) ?>" class="form-stack card">
<?= csrf_field() ?>
<div class="form-group">
    <label class="form-label" for="subject">Subject <span class="required">*</span></label>
    <input id="subject" name="subject" required minlength="3" value="<?= e((string) old('subject')) ?>">
    <?php if (!empty($fieldErrors['subject'][0])): ?>
        <p class="form-error"><?= e($fieldErrors['subject'][0]) ?></p>
    <?php endif; ?>
</div>
<div class="form-grid two-col">
<div class="form-group">
    <label class="form-label" for="category">Category <span class="required">*</span></label>
    <select id="category" name="category" required>
    <?php foreach (['bug' => 'Bug', 'support' => 'Support', 'feature' => 'Feature idea', 'billing' => 'Billing'] as $k => $l): ?>
    <option value="<?= e($k) ?>"<?= old('category', 'bug') === $k ? ' selected' : '' ?>><?= e($l) ?></option>
    <?php endforeach; ?>
    </select>
</div>
<div class="form-group">
    <label class="form-label" for="priority">Priority <span class="required">*</span></label>
    <select id="priority" name="priority" required>
    <?php foreach (['low','normal','high','urgent'] as $p): ?>
    <option value="<?= e($p) ?>"<?= old('priority', 'normal') === $p ? ' selected' : '' ?>><?= e(ucfirst($p)) ?></option>
    <?php endforeach; ?>
    </select>
</div>
</div>
<div class="form-group">
    <label class="form-label" for="body">Details <span class="required">*</span></label>
    <textarea id="body" name="body" rows="8" required minlength="5"><?= e((string) old('body')) ?></textarea>
    <?php if (!empty($fieldErrors['body'][0])): ?>
        <p class="form-error"><?= e($fieldErrors['body'][0]) ?></p>
    <?php endif; ?>
</div>
<div class="form-actions">
    <button class="btn btn-primary" type="submit">Submit ticket</button>
    <a class="btn btn-secondary" href="<?= e(url('/member/support')) ?>">Cancel</a>
</div>
</form>
