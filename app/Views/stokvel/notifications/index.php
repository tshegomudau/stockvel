<?= view('partials/page-header', [
    'title' => 'Notifications',
    'description' => 'In-app alerts for membership, payments, meetings, and security events.',
], null) ?>
<form method="post" action="<?= e(url('/app/notifications/read-all')) ?>" class="mb-4"><?= csrf_field() ?><button class="btn btn-secondary btn-sm" type="submit">Mark all read</button></form>
<?php $list = $items ?? $rows ?? $notifications ?? []; ?>
<?php if (empty($list)): ?>
    <?= view('partials/empty', ['emptyTitle' => 'You are all caught up'], null) ?>
<?php else: ?>
<ul class="activity-list">
<?php foreach ($list as $row): ?>
<li class="activity-item">
<div>
<strong><?= e($row['title'] ?? $row['subject'] ?? 'Notification') ?></strong>
<div class="text-sm text-muted"><?= e($row['body'] ?? $row['message'] ?? '') ?></div>
</div>
<time><?= e(sa_datetime($row['created_at'] ?? '')) ?></time>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
