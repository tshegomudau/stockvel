<?= view('partials/page-header', [
    'title' => 'Announcements',
    'description' => 'Notices published by your stokvel committee.',
], null) ?>
<?php if (empty($items)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No announcements',
        'emptyMessage' => 'There are no published announcements right now.',
    ], null) ?>
<?php else: ?>
    <?php foreach ($items as $item): ?>
        <?php $aid = (int) ($item['id'] ?? 0); ?>
        <article class="card mb-6">
            <header class="mb-4">
                <h2><?= e($item['title'] ?? '') ?></h2>
                <p class="text-muted">
                    <?= e(sa_datetime($item['publish_at'] ?? $item['created_at'] ?? '')) ?>
                    · <?= e($item['priority'] ?? 'normal') ?>
                    <?php if (!empty($item['read_at'])): ?>
                        · Read <?= e(sa_datetime($item['read_at'])) ?>
                    <?php else: ?>
                        · <strong>Unread</strong>
                    <?php endif; ?>
                </p>
            </header>
            <div class="prose"><?= safe_html($item['body_html'] ?? '') ?></div>
            <?php if (empty($item['read_at'])): ?>
                <form method="post" action="<?= e(url('/member/announcements/' . $aid . '/read')) ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary btn-sm">Mark as read</button>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
