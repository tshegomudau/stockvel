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
    'title' => 'New rule',
    'description' => 'Publish a stokvel rule or constitution clause for members to acknowledge.',
], null) ?>

<div class="card">
    <form method="post" action="<?= e(url('/app/rules')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="title">Title <span class="required">*</span></label>
            <input type="text" id="title" name="title" value="<?= e((string) old('title')) ?>" required maxlength="255">
            <?php if (!empty($fieldErrors['title'][0])): ?>
                <p class="form-error"><?= e($fieldErrors['title'][0]) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category">
                <?php
                $cat = (string) old('category', 'general');
                foreach ($categories as $val => $label):
                ?>
                    <option value="<?= e((string) $val) ?>"<?= $cat === (string) $val ? ' selected' : '' ?>><?= e((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="help-text"><a href="<?= e(url('/app/rules/categories')) ?>">Manage rule categories</a></p>
            <?php if (!empty($fieldErrors['category'][0])): ?>
                <p class="form-error"><?= e($fieldErrors['category'][0]) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label for="body">Rule text <span class="required">*</span></label>
            <textarea id="body" name="body" rows="8" required><?= e((string) old('body')) ?></textarea>
            <?php if (!empty($fieldErrors['body'][0])): ?>
                <p class="form-error"><?= e($fieldErrors['body'][0]) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save rule</button>
            <a class="btn btn-secondary" href="<?= e(url('/app/rules')) ?>">Cancel</a>
        </div>
    </form>
</div>
