<?php $itemId = (int) ($item['id'] ?? 0); $status = (string) ($item['status'] ?? 'draft'); ?>
<?= view('partials/page-header', [
    'title' => $item['title'] ?? 'Announcement',
    'description' => 'Status: ' . $status,
], null) ?>
<p class="text-muted"><a href="<?= e(url('/app/announcements')) ?>">&larr; All announcements</a></p>

<div class="card mb-6">
    <dl class="detail-list">
        <dt>Priority</dt><dd><span class="badge"><?= e($item['priority'] ?? 'normal') ?></span></dd>
        <dt>Audience</dt><dd><?= e($audienceLabel ?? ($item['audience'] ?? 'all')) ?></dd>
        <dt>Status</dt><dd><span class="badge"><?= e($status) ?></span></dd>
        <dt>Publish at</dt><dd><?= e(sa_datetime($item['publish_at'] ?? '') ?: '—') ?></dd>
        <dt>Expires</dt><dd><?= e(sa_datetime($item['expire_at'] ?? '') ?: '—') ?></dd>
        <dt>Author</dt><dd><?= e(trim((string) ($item['author_name'] ?? '')) !== '' ? $item['author_name'] : '—') ?></dd>
        <dt>Reads</dt><dd><?= (int) ($readCount ?? 0) ?></dd>
    </dl>
    <div class="prose mt-4"><?= safe_html($item['body_html'] ?? '') ?></div>
    <div class="form-actions mt-4">
        <?php if (in_array($status, ['draft', 'scheduled'], true)): ?>
            <a class="btn btn-secondary" href="<?= e(url('/app/announcements/' . $itemId . '/edit')) ?>">Edit</a>
            <form method="post" action="<?= e(url('/app/announcements/' . $itemId . '/publish')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary" data-confirm="Publish this announcement now?">Publish now</button>
            </form>
        <?php endif; ?>
    </div>
</div>
