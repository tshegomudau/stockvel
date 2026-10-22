<?= view('partials/page-header', [
    'title' => 'Support tickets',
    'description' => 'Review and update tenant bug/support requests.',
], null) ?>
<form method="get" class="filter-bar mb-4">
<select name="status" onchange="this.form.submit()">
<option value="">All statuses</option>
<?php foreach (['open','in_progress','waiting','resolved','closed'] as $st): ?>
<option value="<?= e($st) ?>"<?= ($statusFilter ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_',' ',$st))) ?></option>
<?php endforeach; ?>
</select>
</form>
<?php if (empty($paginator->items)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'No tickets'], null) ?>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>#</th><th>Subject</th><th>Reporter</th><th>Status</th><th>Priority</th><th></th></tr></thead>
<tbody>
<?php foreach ($paginator->items as $row): ?>
<tr>
<td>#<?= (int) $row['id'] ?></td>
<td><?= e($row['subject'] ?? '') ?></td>
<td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: ($row['email'] ?? '')) ?></td>
<td><?= e($row['status'] ?? '') ?></td>
<td><?= e($row['priority'] ?? '') ?></td>
<td><a href="<?= e(url('/platform/support/' . $row['id'])) ?>">Open</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
