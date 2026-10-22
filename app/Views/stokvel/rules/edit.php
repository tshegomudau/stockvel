<?php
use App\Core\Session;
$fieldErrors = Session::getFlash('errors', []);
if (!is_array($fieldErrors)) {
    $fieldErrors = [];
}
$categories = $categories ?? [
    'general' => 'General',
    'constitution' => 'Constitution',
    'contributions' => 'Contributions',
    'loans' => 'Loans',
    'meetings' => 'Meetings',
    'payouts' => 'Payouts',
    'discipline' => 'Discipline',
    'other' => 'Other',
];
?>
<?= view('partials/page-header', [
    'title' => 'Edit rule',
    'description' => 'Updating the rule text publishes a new version.',
], null) ?>

<div class="card">
    <form method="post" action="<?= e(url('/app/rules/' . ($item['id'] ?? ''))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <div class="form-group">
            <label for="title">Title <span class="required">*</span></label>
            <input type="text" id="title" name="title" value="<?= e((string) old('title', (string) ($item['title'] ?? ''))) ?>" required maxlength="255">
            <?php if (!empty($fieldErrors['title'][0])): ?>
                <p class="form-error"><?= e($fieldErrors['title'][0]) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category">
                <?php
                $cat = (string) old('category', (string) ($item['category'] ?? 'general'));
                foreach ($categories as $val => $label):
                ?>
                    <option value="<?= e((string) $val) ?>"<?= $cat === (string) $val ? ' selected' : '' ?>><?= e((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (!empty($fieldErrors['category'][0])): ?>
                <p class="form-error"><?= e($fieldErrors['category'][0]) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="body">Rule text <span class="required">*</span></label>
            <textarea id="body" name="body" rows="8" required><?= e((string) old('body', (string) ($item['body'] ?? ''))) ?></textarea>
            <?php if (!empty($fieldErrors['body'][0])): ?>
                <p class="form-error"><?= e($fieldErrors['body'][0]) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update rule</button>
            <a class="btn btn-secondary" href="<?= e(url('/app/rules/' . ($item['id'] ?? ''))) ?>">Cancel</a>
        </div>
    </form>
</div>
