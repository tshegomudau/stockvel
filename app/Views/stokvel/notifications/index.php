<form method="post" action="<?= e(url('/app/notifications/read-all')) ?>" class="toolbar">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-sm">Mark all read</button>
</form>
<ul class="notification-list">
    <?php foreach ($paginator->items as $note): ?>
        <li class="notification-item<?= empty($note['read_at']) ? ' is-unread' : '' ?>">
            <div>
                <strong><?= e($note['title'] ?? 'Notification') ?></strong>
                <p><?= e($note['body'] ?? '') ?></p>
                <small><?= e(sa_datetime($note['created_at'] ?? '')) ?></small>
            </div>
            <?php if (empty($note['read_at'])): ?>
                <form method="post" action="<?= e(url('/app/notifications/' . ($note['id'] ?? '') . '/read')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm">Mark read</button>
                </form>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
<?= view('partials/pagination', ['paginator' => $paginator], null) ?>
