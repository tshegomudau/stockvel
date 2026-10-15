<div class="card">
    <h1>New Loan</h1>
    <form method="post" action="<?= e(url('/app/loans')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group"><label for="member_id">Member ID</label><input type="number" id="member_id" name="member_id" value="<?= e((string) old('member_id', (string) ($item['member_id'] ?? ''))) ?>" required></div>
        <div class="form-group"><label for="principal">Principal</label><input type="number" id="principal" name="principal" value="<?= e((string) old('principal', (string) ($item['principal'] ?? ''))) ?>" required step="0.01"></div>
        <div class="form-group"><label for="interest_rate">Interest rate %</label><input type="number" id="interest_rate" name="interest_rate" value="<?= e((string) old('interest_rate', (string) ($item['interest_rate'] ?? ''))) ?>" required step="0.01"></div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>
