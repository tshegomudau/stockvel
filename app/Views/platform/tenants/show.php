<div class="page-header">
    <h1><?= e($stokvel['name'] ?? 'Stokvel') ?></h1>
    <span class="badge"><?= e($stokvel['status'] ?? '') ?></span>
</div>
<div class="grid grid-2">
    <div class="card">
        <h2>Details</h2>
        <dl class="detail-list">
            <dt>Registration</dt><dd><?= e($stokvel['registration_number'] ?? '—') ?></dd>
            <dt>Created</dt><dd><?= e(sa_datetime($stokvel['created_at'] ?? '')) ?></dd>
        </dl>
        <?php if (($stokvel['status'] ?? '') === 'active'): ?>
            <form method="post" action="<?= e(url('/platform/tenants/' . ($stokvel['id'] ?? '') . '/suspend')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger" data-confirm="Suspend this stokvel?">Suspend</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('/platform/tenants/' . ($stokvel['id'] ?? '') . '/activate')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Activate</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Members (recent)</h2>
        <table class="table table-compact">
            <thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($members as $m): ?>
                <tr>
                    <td><?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?></td>
                    <td><?= e($m['role'] ?? '') ?></td>
                    <td><?= e($m['status'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
