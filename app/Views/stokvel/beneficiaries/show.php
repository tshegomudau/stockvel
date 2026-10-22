<?php
$item = $item ?? [];
$itemId = (int) ($item['id'] ?? 0);
$status = (string) ($item['status'] ?? 'active');
$memberName = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
$canManage = !empty($canManage);
$statusClass = $status === 'active' ? 'badge-success' : 'badge-warning';
?>
<?= view('partials/page-header', [
    'title' => (string) ($item['full_name'] ?? 'Beneficiary'),
    'description' => 'Beneficiary record for ' . ($memberName !== '' ? $memberName : 'member') . '.',
    'actionUrl' => $canManage ? url('/app/beneficiaries/' . $itemId . '/edit') : null,
    'actionLabel' => $canManage ? 'Edit' : null,
], null) ?>

<div class="card mb-6">
    <dl class="detail-list">
        <dt>Status</dt>
        <dd>
            <span class="badge <?= e($statusClass) ?>"><?= e(ucfirst($status)) ?></span>
            <?php if (!empty($item['is_primary'])): ?>
                <span class="badge badge-primary">Primary</span>
            <?php endif; ?>
        </dd>
        <dt>Member</dt>
        <dd>
            <a href="<?= e(url('/app/members/' . (int) ($item['membership_id'] ?? 0))) ?>">
                <?= e($memberName !== '' ? $memberName : 'Member') ?>
            </a>
            <?php if (!empty($item['member_number'])): ?>
                <span class="text-muted">(<?= e((string) $item['member_number']) ?>)</span>
            <?php endif; ?>
        </dd>
        <dt>Relationship</dt>
        <dd><?= e((string) ($item['relationship'] ?? '—')) ?></dd>
        <dt>Share</dt>
        <dd><?= e(number_format((float) ($item['share_percent'] ?? 0), 2)) ?>%</dd>
        <dt>ID number</dt>
        <dd><?= e((string) ($item['id_number'] ?? '') !== '' ? (string) $item['id_number'] : '—') ?></dd>
        <dt>Phone</dt>
        <dd><?= e((string) ($item['phone'] ?? '') !== '' ? (string) $item['phone'] : '—') ?></dd>
        <dt>Email</dt>
        <dd><?= e((string) ($item['email'] ?? '') !== '' ? (string) $item['email'] : '—') ?></dd>
        <dt>Created</dt>
        <dd><?= e(sa_datetime($item['created_at'] ?? null)) ?></dd>
        <dt>Updated</dt>
        <dd><?= e(sa_datetime($item['updated_at'] ?? null)) ?></dd>
    </dl>
</div>

<?php if ($canManage): ?>
<div class="toolbar">
    <?php if ($status === 'active'): ?>
        <form method="post" action="<?= e(url('/app/beneficiaries/' . $itemId . '/deactivate')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost">Deactivate</button>
        </form>
    <?php else: ?>
        <form method="post" action="<?= e(url('/app/beneficiaries/' . $itemId . '/reactivate')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-secondary">Reactivate</button>
        </form>
        <form method="post" action="<?= e(url('/app/beneficiaries/' . $itemId . '/destroy')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-danger" data-confirm="Permanently delete this inactive beneficiary?">Delete permanently</button>
        </form>
    <?php endif; ?>
    <a class="btn btn-secondary" href="<?= e(url('/app/beneficiaries')) ?>">Back to list</a>
</div>
<?php else: ?>
<p><a class="btn btn-secondary" href="<?= e(url('/app/beneficiaries')) ?>">Back to list</a></p>
<?php endif; ?>
