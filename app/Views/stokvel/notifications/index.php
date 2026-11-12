<?= view('partials/page-header', [
    'title' => 'Notifications',
    'description' => 'In-app alerts for membership, payments, meetings, and security events.',
], null) ?>
<form method="post" action="<?= e(url('/app/notifications/read-all')) ?>" class="mb-4"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit">Mark all read</button></form>
<?php
$paginator = $paginator ?? null;
$list = [];
if (is_object($paginator) && isset($paginator->items) && is_array($paginator->items)) {
    $list = $paginator->items;
} else {
    $list = $items ?? $rows ?? $notifications ?? [];
}
?>
<?php if ($list === []): ?>
    <?= view('partials/empty', ['emptyTitle' => 'You are all caught up'], null) ?>
<?php else: ?>
<ul class="activity-list notification-list">
<?php foreach ($list as $row): ?>
<li class="activity-item notification-item<?= empty($row['read_at']) ? ' is-unread' : '' ?>">
<div>
<strong><?= e($row['title'] ?? $row['subject'] ?? 'Notification') ?></strong>
<div class="text-sm text-muted"><?= e($row['body'] ?? $row['message'] ?? '') ?></div>
</div>
<div class="activity-meta">
<time><?= e(sa_datetime($row['created_at'] ?? '')) ?></time>
<?php if (empty($row['read_at']) && !empty($row['id'])): ?>
<form method="post" action="<?= e(url('/app/notifications/' . (int) $row['id'] . '/read')) ?>" class="inline-form">
<?= csrf_field() ?>
<button type="submit" class="btn btn-sm btn-secondary">Mark read</button>
</form>
<?php endif; ?>
</div>
</li>
<?php endforeach; ?>
</ul>
<?php if (is_object($paginator)): ?>
    <?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
<?php endif; ?>
