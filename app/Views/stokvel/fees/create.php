<div class="card">
    <h1>New Fee structure</h1>
    <form method="post" action="<?= e(url('/app/fees')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group"><label for="name">Name</label><input type="text" id="name" name="name" value="<?= e((string) old('name', (string) ($item['name'] ?? ''))) ?>" required></div>
        <div class="form-group"><label for="amount">Amount (ZAR)</label><input type="number" id="amount" name="amount" value="<?= e((string) old('amount', (string) ($item['amount'] ?? ''))) ?>" required step="0.01"></div>
        <div class="form-group"><label for="frequency">Frequency</label><select id="frequency" name="frequency" required><option value="monthly">Monthly</option><option value="weekly">Weekly</option><option value="once">Once</option></select></div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>
