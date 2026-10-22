<div class="card">
    <h1>New document category</h1>
    <form method="post" action="<?= e(url('/app/documents/categories')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group"><label for="code">Code</label><input id="code" name="code" required value="<?= e(old('code', '')) ?>"></div>
        <div class="form-group"><label for="name">Name</label><input id="name" name="name" required value="<?= e(old('name', '')) ?>"></div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/documents/categories')) ?>">Cancel</a>
    </form>
</div>
