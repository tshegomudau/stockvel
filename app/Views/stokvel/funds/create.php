<?= view('partials/page-header', [
    'title' => 'New fund',
    'description' => 'Create an internal accounting pocket. This is not a bank account.',
], null) ?>
<form method="post" action="<?= e(url('/app/funds')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="code">Code</label>
            <input id="code" name="code" required value="<?= e(old('code', '')) ?>" placeholder="reserve">
        </div>
        <div class="form-group">
            <label class="form-label" for="name">Name</label>
            <input id="name" name="name" required value="<?= e(old('name', '')) ?>" placeholder="Reserve fund">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="description">Description</label>
        <textarea id="description" name="description" rows="3"><?= e(old('description', '')) ?></textarea>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create fund</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/funds')) ?>">Cancel</a>
    </div>
</form>
