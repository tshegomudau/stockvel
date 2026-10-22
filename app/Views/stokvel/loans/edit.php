<div class="card">
    <h1>Edit application</h1>
    <p class="text-muted">Only the purpose can be changed while the application is awaiting committee review.</p>
    <form method="post" action="<?= e(url('/app/loans/' . ($item['id'] ?? ''))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <div class="form-group"><label for="purpose">Purpose</label><input type="text" id="purpose" name="purpose" value="<?= e((string) old('purpose', (string) ($item['purpose'] ?? ''))) ?>" maxlength="255"></div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/loans/' . ($item['id'] ?? ''))) ?>">Cancel</a>
    </form>
</div>
