<?php $cat = $category ?? []; ?>
<div class="card">
    <h1>Edit document category</h1>
    <form method="post" action="<?= e(url('/app/documents/categories/' . (int) ($cat['id'] ?? 0))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <p class="text-muted">Code: <strong><?= e($cat['code'] ?? '') ?></strong></p>
        <div class="form-group"><label for="name">Name</label><input id="name" name="name" required value="<?= e(old('name', $cat['name'] ?? '')) ?>"></div>
        <button type="submit" class="btn btn-primary">Update</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/documents/categories')) ?>">Cancel</a>
    </form>
</div>
