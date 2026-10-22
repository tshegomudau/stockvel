<?php $cat = $category ?? []; ?>
<div class="card">
    <h1>Edit payout category</h1>
    <p class="text-muted">Code: <strong><?= e($cat['code'] ?? '') ?></strong></p>
    <form method="post" action="<?= e(url('/app/payouts/categories/' . (int) ($cat['id'] ?? 0))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <div class="form-group"><label for="name">Name</label><input id="name" name="name" required value="<?= e(old('name', $cat['name'] ?? '')) ?>"></div>
        <div class="form-group"><label for="description">Description</label><textarea id="description" name="description" rows="2"><?= e(old('description', $cat['description'] ?? '')) ?></textarea></div>
        <button type="submit" class="btn btn-primary">Update</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/payouts/categories')) ?>">Cancel</a>
    </form>
</div>
