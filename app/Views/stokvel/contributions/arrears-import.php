<?= view('partials/page-header', [
    'title' => 'Import arrears',
    'description' => 'Bulk-capture historical arrears from CSV. Match members by email.',
], null) ?>

<div class="card form-stack">
    <p><a class="btn btn-secondary" href="<?= e(url('/app/contributions/arrears/import/template')) ?>">Download CSV template</a></p>
    <form method="post" action="<?= e(url('/app/contributions/arrears/import')) ?>" enctype="multipart/form-data" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label required" for="csv">CSV file</label>
            <input id="csv" type="file" name="csv" accept=".csv,text/csv" required>
            <p class="form-help">Columns: email, amount, due_date, description, period_label</p>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Import</button>
            <a class="btn btn-secondary" href="<?= e(url('/app/contributions/arrears')) ?>">Cancel</a>
        </div>
    </form>
</div>
