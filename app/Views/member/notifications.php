<?= view('partials/page-header', [
    'title' => 'Notifications',
    'description' => 'Alerts about your membership, contributions, and change requests.',
], null) ?>
<?php
$items = is_object($paginator ?? null) && isset($paginator->items) ? $paginator->items : [];
?>
<?php if ($items === []): ?>
    <?= view('partials/empty', ['emptyTitle' => 'You are all caught up'], null) ?>
<?php else: ?>
<ul class="notification-list activity-list">
    <?php foreach ($items as $note): ?>
        <li class="notification-item activity-item<?= empty($note['read_at']) ? ' is-unread' : '' ?>">
            <div>
                <strong><?= e($note['title'] ?? '') ?></strong>
                <p class="text-sm text-muted"><?= e($note['body'] ?? '') ?></p>
                <small><?= e(sa_datetime($note['created_at'] ?? '')) ?></small>
            </div>
            <?php if (empty($note['read_at'])): ?>
                <form method="post" action="<?= e(url('/member/notifications/' . ($note['id'] ?? '') . '/read')) ?>" class="inline-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-secondary">Mark read</button>
                </form>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
