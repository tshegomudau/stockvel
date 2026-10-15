<div class="card">
    <h1>Edit Payout</h1>
    <form method="post" action="<?= e(url('/app/payouts/' . ($item['id'] ?? ''))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <div class="form-group"><label for="member_id">Member ID</label><input type="number" id="member_id" name="member_id" value="<?= e((string) old('member_id', (string) ($item['member_id'] ?? ''))) ?>" required></div>
        <div class="form-group"><label for="amount">Amount</label><input type="number" id="amount" name="amount" value="<?= e((string) old('amount', (string) ($item['amount'] ?? ''))) ?>" required step="0.01"></div>
        <div class="form-group"><label for="scheduled_at">Scheduled date</label><input type="date" id="scheduled_at" name="scheduled_at" value="<?= e((string) old('scheduled_at', (string) ($item['scheduled_at'] ?? ''))) ?>" required></div>
        <button type="submit" class="btn btn-primary">Update</button>
    </form>
</div>
