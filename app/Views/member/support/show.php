<?= view('partials/page-header', [
    'title' => 'Ticket #' . (int) ($ticket['id'] ?? 0),
    'description' => (string) ($ticket['subject'] ?? ''),
], null) ?>
<div class="card mb-4">
<p><strong>Status:</strong> <?= e($ticket['status'] ?? '') ?> · <strong>Category:</strong> <?= e($ticket['category'] ?? '') ?></p>
<div class="prose"><?= nl2br(e($ticket['body'] ?? '')) ?></div>
</div>
<?php foreach ($updates ?? [] as $u): ?>
<div class="card mb-3">
<p class="text-muted"><?= e(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'User') ?> · <?= e(sa_datetime($u['created_at'] ?? '')) ?></p>
<div><?= nl2br(e($u['body'] ?? '')) ?></div>
</div>
<?php endforeach; ?>
<form method="post" action="<?= e(url('/member/support/' . (int) $ticket['id'] . '/reply')) ?>" class="form-stack">
<?= csrf_field() ?>
<div class="form-group"><label class="form-label" for="body">Add update</label>
<textarea id="body" name="body" rows="4" required></textarea></div>
<button class="btn btn-primary" type="submit">Reply</button>
</form>
<p class="mt-4"><a href="<?= e(url('/member/support')) ?>">← All tickets</a></p>
