<div class="card">
    <h1>Bulk capture</h1>
    <form method="post" action="<?= e(url('/app/contributions/bulk')) ?>">
        <?= csrf_field() ?>
        <table class="table">
            <thead><tr><th>Member</th><th>Amount</th><th>Date</th><th>Method</th></tr></thead>
            <tbody>
            <?php foreach ($members as $i => $m): ?>
                <tr>
                    <td>
                        <?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                        <input type="hidden" name="rows[<?= (int) $i ?>][member_id]" value="<?= e((string) $m['id']) ?>">
                    </td>
                    <td><input type="number" step="0.01" name="rows[<?= (int) $i ?>][amount]" placeholder="0.00"></td>
                    <td><input type="date" name="rows[<?= (int) $i ?>][paid_at]" value="<?= e(date('Y-m-d')) ?>"></td>
                    <td>
                        <?php $paymentMethods = $paymentMethods ?? \App\Services\Finance\ContributionService::PAYMENT_METHODS; ?>
                        <select name="rows[<?= (int) $i ?>][payment_method]" aria-label="Payment method for member">
                            <?php foreach ($paymentMethods as $pm): ?>
                                <option value="<?= e($pm) ?>"<?= $pm === 'eft' ? ' selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $pm))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" class="btn btn-primary">Save bulk entries</button>
    </form>
</div>
