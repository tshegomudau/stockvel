<div class="card">
    <h1>Edit Rule</h1>
    <form method="post" action="<?= e(url('/app/rules/' . ($item['id'] ?? ''))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <div class="form-group"><label for="title">Title</label><input type="text" id="title" name="title" value="<?= e((string) old('title', (string) ($item['title'] ?? ''))) ?>" required></div>
        <div class="form-group"><label for="body">Rule text</label><textarea id="body" name="body" rows="4" required><?= e((string) old('body', (string) ($item['body'] ?? ''))) ?></textarea></div>
        <button type="submit" class="btn btn-primary">Update</button>
    </form>
</div>
