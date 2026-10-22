<?= view('partials/page-header', [
    'title' => 'Ticket #' . (int) ($ticket['id'] ?? 0),
    'description' => (string) ($ticket['subject'] ?? ''),
], null) ?>
<div class="card mb-4">
<p>Reporter: <?= e(trim(($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? '')) . ' · ' . ($ticket['email'] ?? '')) ?></p>
<div class="prose"><?= nl2br(e($ticket['body'] ?? '')) ?></div>
</div>
<?php foreach ($updates ?? [] as $u): ?>
<div class="card mb-3">
<p class="text-muted"><?= e(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'Staff') ?>
<?php if (!empty($u['status'])): ?> · <?= e($u['status']) ?><?php endif; ?>
 · <?= e(sa_datetime($u['created_at'] ?? '')) ?></p>
<div><?= nl2br(e($u['body'] ?? '')) ?></div>
</div>
<?php endforeach; ?>
<form method="post" action="<?= e(url('/platform/support/' . (int) $ticket['id'])) ?>" class="form-stack">
<?= csrf_field() ?>
<div class="form-group"><label class="form-label" for="status">Status</label>
<select id="status" name="status">
<?php foreach (['open','in_progress','waiting','resolved','closed'] as $st): ?>
<option value="<?= e($st) ?>"<?= ($ticket['status'] ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_',' ',$st))) ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-group"><label class="form-label" for="body">Public update</label>
<textarea id="body" name="body" rows="4" placeholder="Visible to the reporter"></textarea></div>
<button class="btn btn-primary" type="submit">Update ticket</button>
</form>
