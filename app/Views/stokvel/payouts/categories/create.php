<div class="card">
    <h1>New payout category</h1>
    <form method="post" action="<?= e(url('/app/payouts/categories')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group"><label for="code">Code</label><input id="code" name="code" required value="<?= e(old('code', '')) ?>" placeholder="custom_benefit"></div>
        <div class="form-group"><label for="name">Name</label><input id="name" name="name" required value="<?= e(old('name', '')) ?>"></div>
        <div class="form-group"><label for="description">Description</label><textarea id="description" name="description" rows="2"><?= e(old('description', '')) ?></textarea></div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/payouts/categories')) ?>">Cancel</a>
    </form>
</div>
