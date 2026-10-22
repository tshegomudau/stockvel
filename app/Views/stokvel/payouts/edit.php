<div class="card">
    <h1>Edit payout cycle</h1>
    <form method="post" action="<?= e(url('/app/payouts/' . ($item['id'] ?? ''))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?= method_field('PUT') ?>
        <div class="form-group"><label for="name">Cycle name</label><input type="text" id="name" name="name" value="<?= e((string) old('name', (string) ($item['name'] ?? ''))) ?>" required></div>
        <div class="form-group">
            <label for="payout_category_id">Category</label>
            <select id="payout_category_id" name="payout_category_id">
                <option value="">— None —</option>
                <?php foreach ($categories ?? [] as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= (string) old('payout_category_id', (string) ($item['payout_category_id'] ?? '')) === (string) $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group"><label for="payout_amount">Payout amount</label><input type="number" id="payout_amount" name="payout_amount" value="<?= e((string) old('payout_amount', (string) ($item['payout_amount'] ?? ''))) ?>" step="0.01"></div>
        <button type="submit" class="btn btn-primary">Update</button>
    </form>
</div>
