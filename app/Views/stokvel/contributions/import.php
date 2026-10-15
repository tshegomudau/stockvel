<div class="card">
    <h1>Import contributions (CSV)</h1>
    <p>Columns: member email or member number, amount, payment date (YYYY-MM-DD).</p>
    <form method="post" action="<?= e(url('/app/contributions/import')) ?>" enctype="multipart/form-data" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="csv">CSV file</label>
            <input type="file" id="csv" name="csv" accept=".csv,text/csv" required>
        </div>
        <button type="submit" class="btn btn-primary">Import</button>
    </form>
</div>
