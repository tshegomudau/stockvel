<?= view('partials/page-header', [
    'title' => 'Create stokvel',
    'description' => 'Creates an active stokvel immediately (approval bypassed). Recorded against your platform admin account.',
], null) ?>
<form method="post" action="<?= e(url('/platform/tenants')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-group">
        <label class="form-label required" for="name">Name</label>
        <input class="form-control" type="text" id="name" name="name" required value="<?= e((string) old('name')) ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="stokvel_type">Type</label>
        <select class="form-control form-select" id="stokvel_type" name="stokvel_type">
            <?php foreach (($stokvelTypeOptions ?? []) as $opt): ?>
                <option value="<?= e((string) $opt['code']) ?>"><?= e((string) $opt['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label" for="description">Description</label>
        <textarea class="form-control form-textarea" id="description" name="description" rows="3"><?= e((string) old('description')) ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary">Create stokvel</button>
</form>
