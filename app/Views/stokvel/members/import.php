<?= view('partials/page-header', [
    'title' => 'Import members',
    'description' => 'Bulk-create members from a CSV. Download the template, fill rows, then upload.',
], null) ?>

<div class="card form-stack">
    <p><a class="btn btn-secondary" href="<?= e(url('/app/members/import/template')) ?>">Download CSV template</a></p>
    <form method="post" action="<?= e(url('/app/members/import')) ?>" enctype="multipart/form-data" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label required" for="csv">CSV file</label>
            <input id="csv" type="file" name="csv" accept=".csv,text/csv" required>
            <p class="form-help">Columns: first_name, last_name, email, mobile, role_code, minimum_contribution, joined_at</p>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Import</button>
            <a class="btn btn-secondary" href="<?= e(url('/app/members')) ?>">Cancel</a>
        </div>
    </form>
</div>
