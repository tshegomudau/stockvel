<?= view('partials/page-header', [
    'title' => $constitution['title'] ?? 'Constitution',
    'description' => 'Current published constitution and version history.',
], null) ?>
<div class="form-actions mb-6">
    <a class="btn btn-primary" href="<?= e(url('/app/constitution/edit')) ?>">Edit draft</a>
</div>

<?php if ($current): ?>
<div class="card mb-6">
    <h2>Current version <?= (int) ($current['version_number'] ?? 0) ?></h2>
    <p class="text-muted">
        Effective <?= e(sa_date($current['effective_from'] ?? '') ?: '—') ?>
        · Published <?= e(sa_datetime($current['approved_at'] ?? '') ?: '—') ?>
    </p>
    <div class="prose"><?= safe_html($current['body_html'] ?? '') ?></div>
</div>
<?php else: ?>
<div class="card mb-6">
    <p class="text-muted">No published constitution yet.<?= !empty($draft) ? ' A draft is ready to edit and publish.' : '' ?></p>
</div>
<?php endif; ?>

<?php if (!empty($draft)): ?>
<div class="callout mb-6">
    Draft version <?= (int) ($draft['version_number'] ?? 0) ?> is in progress.
    <a href="<?= e(url('/app/constitution/edit')) ?>">Continue editing</a>
</div>
<?php endif; ?>

<h2>Version history</h2>
<?php if (empty($versions)): ?>
    <p class="text-muted">No versions yet.</p>
<?php else: ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Version</th><th>Status</th><th>Effective</th><th>Approved</th><th>Created</th></tr></thead>
<tbody>
<?php foreach ($versions as $v): ?>
<tr>
    <td><?= (int) ($v['version_number'] ?? 0) ?></td>
    <td><span class="badge"><?= e($v['status'] ?? '') ?></span></td>
    <td><?= e(sa_date($v['effective_from'] ?? '') ?: '—') ?></td>
    <td><?= e(sa_datetime($v['approved_at'] ?? '') ?: '—') ?></td>
    <td><?= e(sa_datetime($v['created_at'] ?? '') ?: '—') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
