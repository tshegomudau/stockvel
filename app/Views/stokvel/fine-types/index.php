<?= view('partials/page-header', [
    'title' => 'Fine types',
    'description' => 'Catalogue of fine reasons and default amounts.',
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/settings/categories')) ?>">&larr; Category hub</a> · <a href="<?= e(url('/app/fines')) ?>">Fines</a></p>
<form method="get" action="<?= e(url('/app/fine-types')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Name or code">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterQ ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/fine-types')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<form method="post" action="<?= e(url('/app/fine-types')) ?>" class="form-stack card mb-6">
<?= csrf_field() ?>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="code">Code</label><input id="code" name="code" required placeholder="late_meeting"></div>
<div class="form-group"><label class="form-label" for="name">Name</label><input id="name" name="name" required placeholder="Late to meeting"></div>
<div class="form-group"><label class="form-label" for="default_amount">Default amount (ZAR)</label><input id="default_amount" name="default_amount" type="number" step="0.01" min="0" value="0"></div>
<div class="form-group"><label class="form-label" for="description">Description</label><input id="description" name="description" placeholder="Optional"></div>
</div>
<button class="btn btn-primary" type="submit">Add fine type</button>
</form>
<?php if (empty($types)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No fine types', 'emptyMessage' => 'Create types such as late meeting or missed contribution.'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Code</th><th>Name</th><th>Default</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($types as $type): ?>
<tr>
<td><?= e($type['code'] ?? '') ?></td>
<td>
<form method="post" action="<?= e(url('/app/fine-types/' . (int) $type['id'])) ?>" class="inline-form">
<?= csrf_field() ?>
<input name="name" value="<?= e($type['name'] ?? '') ?>">
<input name="default_amount" type="number" step="0.01" min="0" value="<?= e((string) ($type['default_amount'] ?? '0')) ?>" style="max-width:7rem">
<button class="btn btn-sm btn-secondary" type="submit">Save</button>
</form>
</td>
<td class="money"><?= e(money($type['default_amount'] ?? 0)) ?></td>
<td><span class="badge"><?= !empty($type['is_active']) ? 'active' : 'inactive' ?></span></td>
<td class="actions">
    <?php if (!empty($type['is_active'])): ?>
    <form method="post" action="<?= e(url('/app/fine-types/' . (int) $type['id'] . '/deactivate')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Deactivate</button></form>
    <?php else: ?>
    <form method="post" action="<?= e(url('/app/fine-types/' . (int) $type['id'] . '/activate')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit">Reactivate</button></form>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/app/fine-types/' . (int) $type['id'])) ?>" onsubmit="return confirm('Delete this fine type?')" class="inline-form">
    <?= csrf_field() ?><?= method_field('DELETE') ?>
    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
    </form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
