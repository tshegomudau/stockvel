<?= view('partials/page-header', [
    'title' => 'Automatic fines',
    'description' => 'Preview and confirm fines only where the application has unambiguous data. Duplicates are prevented with idempotent keys.',
], null) ?>
<p class="mb-4">
    <a href="<?= e(url('/app/fines')) ?>">← Back to fines</a>
    · <a href="<?= e(url('/app/fine-types')) ?>">Fine types</a>
</p>

<form method="get" action="<?= e(url('/app/fines/auto')) ?>" class="filter-bar mb-4">
    <div class="form-group">
        <label for="rule">Rule</label>
        <select id="rule" name="rule" onchange="this.form.submit()">
            <?php foreach ($rules ?? [] as $key => $label): ?>
                <option value="<?= e($key) ?>"<?= ($rule ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php $preview = $preview ?? ['enabled' => false, 'candidates' => [], 'summary' => '', 'count' => 0, 'total_amount' => '0']; ?>

<section class="card mb-4" aria-label="Preview summary">
    <h2 class="h3">Preview</h2>
    <p><?= e((string) ($preview['summary'] ?? '')) ?></p>
    <?php if (!empty($preview['fine_type'])): ?>
        <p class="text-sm text-muted">
            Fine type: <?= e((string) ($preview['fine_type']['name'] ?? '')) ?>
            · Default amount: <?= e(money($preview['fine_type']['default_amount'] ?? 0)) ?>
            · Total if applied: <?= e(money($preview['total_amount'] ?? 0)) ?>
        </p>
    <?php endif; ?>
</section>

<?php if (empty($preview['candidates'])): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No candidates',
        'emptyMessage' => !empty($preview['enabled'])
            ? 'Nothing matches this rule right now, or fines were already issued (idempotent).'
            : 'Configure an automatic-eligible fine type first.',
    ], null) ?>
<?php else: ?>
<div class="table-wrap mb-4">
    <table class="table">
        <thead>
            <tr>
                <th scope="col">Member</th>
                <th scope="col">Amount</th>
                <th scope="col">Detail</th>
                <th scope="col">Reason</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($preview['candidates'] as $row): ?>
            <tr>
                <td><?= e((string) ($row['member_name'] ?? '')) ?></td>
                <td class="money"><?= e(money($row['amount'] ?? 0)) ?></td>
                <td><?= e((string) ($row['detail'] ?? '')) ?></td>
                <td><?= e((string) ($row['reason'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<form method="post" action="<?= e(url('/app/fines/auto')) ?>" class="form-stack card"
      data-confirm="Issue <?= (int) ($preview['count'] ?? 0) ?> automatic fine(s)? This cannot be undone from this screen.">
    <?= csrf_field() ?>
    <input type="hidden" name="rule" value="<?= e((string) ($rule ?? '')) ?>">
    <input type="hidden" name="confirm" value="1">
    <p>Confirm to issue the fines listed above. Re-running the same rule will not duplicate already-issued automatic fines.</p>
    <button type="submit" class="btn btn-primary">Confirm and issue <?= (int) ($preview['count'] ?? 0) ?> fine(s)</button>
</form>
<?php endif; ?>
