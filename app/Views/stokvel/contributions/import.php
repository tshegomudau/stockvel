<?= view('partials/page-header', [
    'title' => 'Import contributions (CSV)',
    'description' => 'Upload member payments in bulk. Download the template for the correct columns.',
], null) ?>
<div class="card">
    <p class="mb-4">
        <a class="btn btn-secondary" href="<?= e(url('/app/contributions/import/template')) ?>">Download CSV template</a>
    </p>
    <p class="text-muted">
        Required columns: <strong>Member Number or Email</strong>, <strong>Amount</strong>,
        <strong>Payment Method</strong> (cash, eft, card, other), <strong>Payment Date</strong> (YYYY-MM-DD).
        Optional: <strong>Reference</strong>.
    </p>
    <form method="post" action="<?= e(url('/app/contributions/import')) ?>" enctype="multipart/form-data" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="csv">CSV file</label>
            <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
        </div>
        <button type="submit" class="btn btn-primary">Import contributions</button>
    </form>
</div>
