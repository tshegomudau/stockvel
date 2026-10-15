<div class="card">
    <h1>Upload document</h1>
    <form method="post" action="<?= e(url('/app/documents/upload')) ?>" enctype="multipart/form-data" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" required>
        </div>
        <div class="form-group">
            <label for="category">Category</label>
            <select id="category" name="category">
                <?php foreach (['general', 'constitution', 'minutes', 'financial'] as $cat): ?>
                    <option value="<?= e($cat) ?>"><?= e(ucfirst($cat)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="document">File (PDF, JPG, PNG)</label>
            <input type="file" id="document" name="document" required accept=".pdf,.jpg,.jpeg,.png">
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
</div>
