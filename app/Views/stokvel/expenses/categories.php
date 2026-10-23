<?= view('partials/page-header', [
    'title' => 'Expense categories',
    'description' => 'Organise spending for budgets and reports.',
], null) ?>
<form method="get" action="<?= e(url('/app/expenses/categories')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" id="q" name="q" value="<?= e((string) ($filterQ ?? '')) ?>" placeholder="Name or code">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($filterQ ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/expenses/categories')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>
<form method="post" action="<?= e(url('/app/expenses/categories')) ?>" class="form-stack card mb-6">
<?= csrf_field() ?>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="code">Code</label><input id="code" name="code" required placeholder="venue"></div>
<div class="form-group"><label class="form-label" for="name">Name</label><input id="name" name="name" required placeholder="Venue hire"></div>
</div>
<button class="btn btn-primary" type="submit">Add category</button>
</form>
<?php if (empty($categories)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No categories', 'emptyMessage' => 'Create categories such as venue, groceries, or bank charges.'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Code</th><th>Name</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php foreach ($categories as $cat): ?>
<tr>
<td><?= e($cat['code'] ?? '') ?></td>
<td>
<form method="post" action="<?= e(url('/app/expenses/categories/' . (int) $cat['id'])) ?>" class="inline-form">
<?= csrf_field() ?>
<input name="name" value="<?= e($cat['name'] ?? '') ?>">
<button class="btn btn-sm btn-secondary" type="submit">Save</button>
</form>
</td>
<td><span class="badge"><?= !empty($cat['is_active']) ? 'active' : 'inactive' ?></span></td>
<td class="actions">
    <?php if (!empty($cat['is_active'])): ?>
    <form method="post" action="<?= e(url('/app/expenses/categories/' . (int) $cat['id'] . '/deactivate')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" type="submit">Deactivate</button></form>
    <?php else: ?>
    <form method="post" action="<?= e(url('/app/expenses/categories/' . (int) $cat['id'] . '/activate')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn-sm btn-primary" type="submit">Reactivate</button></form>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/app/expenses/categories/' . (int) $cat['id'])) ?>" onsubmit="return confirm('Delete this category?')" class="inline-form">
    <?= csrf_field() ?><?= method_field('DELETE') ?>
    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
    </form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<a class="btn btn-secondary mt-4" href="<?= e(url('/app/expenses')) ?>">Back to expenses</a>
