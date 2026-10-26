<?= view('partials/page-header', [
    'title' => 'Loan purposes',
    'description' => 'Tenant catalogue of reasons members may request a loan. Deactivate instead of deleting used values.',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/settings/categories')) ?>">&larr; Category hub</a> · <a href="<?= e(url('/app/loans')) ?>">Loans</a></p>
<form method="get" action="<?= e(url('/app/loans/purposes')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Name or code">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterQ ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/loans/purposes')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<form method="post" action="<?= e(url('/app/loans/purposes')) ?>" class="form-stack card mb-6">
<?= csrf_field() ?>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="code">Code</label><input id="code" name="code" required placeholder="emergency"></div>
<div class="form-group"><label class="form-label" for="name">Name</label><input id="name" name="name" required placeholder="Emergency"></div>
<div class="form-group"><label class="form-label" for="sort_order">Sort order</label><input id="sort_order" name="sort_order" type="number" value="100"></div>
</div>
<button class="btn btn-primary" type="submit">Add purpose</button>
</form>
<?php if (empty($purposes)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No loan purposes', 'emptyMessage' => 'Add purposes so applications can select a catalogue value.'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
    <thead><tr><th>Code</th><th>Name</th><th>Order</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($purposes as $row): ?>
<tr>
<td><?= e($row['code'] ?? '') ?></td>
<td colspan="2">
<form method="post" action="<?= e(url('/app/loans/purposes/' . (int) $row['id'])) ?>" class="inline-form form-grid two-col">
<?= csrf_field() ?>
<input name="name" value="<?= e($row['name'] ?? '') ?>" aria-label="Purpose name">
<input name="sort_order" type="number" value="<?= (int) ($row['sort_order'] ?? 0) ?>" aria-label="Sort order" style="max-width:6rem">
<button class="btn btn-sm btn-secondary" type="submit">Save</button>
</form>
</td>
<td><span class="badge"><?= !empty($row['is_active']) ? 'active' : 'inactive' ?></span></td>
<td class="actions">
    <?php if (!empty($row['is_active'])): ?>
    <form method="post" action="<?= e(url('/app/loans/purposes/' . (int) $row['id'] . '/deactivate')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Deactivate</button></form>
    <?php else: ?>
    <form method="post" action="<?= e(url('/app/loans/purposes/' . (int) $row['id'] . '/activate')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit">Reactivate</button></form>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
