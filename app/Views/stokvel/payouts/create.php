<div class="card">
    <h1>New Payout</h1>
    <form method="post" action="<?= e(url('/app/payouts')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group"><label for="name">Cycle name</label><input type="text" id="name" name="name" value="<?= e((string) old('name', 'Monthly rotation')) ?>" required></div>
        <div class="form-group">
            <label for="member_id">Member</label>
            <select id="member_id" name="member_id" required>
                <option value="">Select member</option>
                <?php foreach ($members ?? [] as $member): ?>
                    <option value="<?= e((string) $member['id']) ?>" <?= (string) old('member_id') === (string) $member['id'] ? 'selected' : '' ?>>
                        <?= e(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label for="amount">Amount</label><input type="number" id="amount" name="amount" value="<?= e((string) old('amount', '')) ?>" required step="0.01"></div>
        <div class="form-group"><label for="scheduled_at">Scheduled date</label><input type="date" id="scheduled_at" name="scheduled_at" value="<?= e((string) old('scheduled_at', date('Y-m-d'))) ?>" required></div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>
