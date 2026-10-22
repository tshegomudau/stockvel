<?php
$latest = $versions[0] ?? null;
?>
<?= view('partials/page-header', [
    'title' => $item['title'] ?? 'Rule',
    'description' => 'Status: ' . ucfirst((string) ($item['status'] ?? 'active'))
        . (!empty($item['category']) ? ' · ' . ucfirst((string) $item['category']) : ''),
    'actionUrl' => url('/app/rules/' . (int) ($item['id'] ?? 0) . '/edit'),
    'actionLabel' => 'Edit',
], null) ?>

<article class="card prose mb-6">
    <?php if ($latest && !empty($latest['content'])): ?>
        <p class="text-muted mb-4">
            Version <?= (int) ($latest['version_number'] ?? 1) ?>
            <?php if (!empty($latest['effective_from'])): ?>
                · Effective <?= e(sa_date($latest['effective_from'])) ?>
            <?php endif; ?>
        </p>
        <div class="rule-body"><?= nl2br(e((string) $latest['content'])) ?></div>
    <?php else: ?>
        <p class="text-muted">No rule text has been published yet.</p>
    <?php endif; ?>
</article>

<?php if (!empty($versions) && count($versions) > 1): ?>
<section class="card">
    <h2 class="card-title">Version history</h2>
    <ul class="plain-list">
        <?php foreach ($versions as $v): ?>
            <li>
                Version <?= (int) ($v['version_number'] ?? 0) ?>
                · <?= e(sa_datetime($v['created_at'] ?? '')) ?>
                <?php if (!empty($v['content'])): ?>
                    <div class="text-muted mt-2"><?= e(mb_substr((string) $v['content'], 0, 160)) ?><?= mb_strlen((string) $v['content']) > 160 ? '…' : '' ?></div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<form method="post" action="<?= e(url('/app/rules/' . (int) ($item['id'] ?? 0))) ?>" class="mt-6">
    <?= csrf_field() ?>
    <?= method_field('DELETE') ?>
    <button type="submit" class="btn btn-danger" data-confirm="Deactivate this rule?">Deactivate rule</button>
</form>
