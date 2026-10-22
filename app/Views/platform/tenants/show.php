<div class="page-header">
    <h1><?= e($stokvel['name'] ?? 'Stokvel') ?></h1>
    <span class="badge"><?= e($stokvel['status'] ?? '') ?></span>
</div>
<div class="grid grid-2">
    <div class="card">
        <h2>Details</h2>
        <dl class="detail-list">
            <dt>Registration</dt><dd><?= e($stokvel['registration_number'] ?? '—') ?></dd>
            <dt>Type</dt><dd><?= e($stokvelTypes[$stokvel['stokvel_type'] ?? ''] ?? ($stokvel['stokvel_type'] ?? '—')) ?></dd>
            <dt>Created</dt><dd><?= e(sa_datetime($stokvel['created_at'] ?? '')) ?></dd>
            <dt>Chairperson</dt>
            <dd>
                <?php $chairRow = $chair ?? $chairperson ?? null; ?>
                <?php if (!empty($chairRow)): ?>
                    <?= e(trim(($chairRow['first_name'] ?? '') . ' ' . ($chairRow['last_name'] ?? ''))) ?>
                    (<?= e($chairRow['email'] ?? '') ?>)
                <?php else: ?>
                    <span class="text-warning">None</span>
                    — <a href="<?= e(url('/platform/users/appoint/' . (int) ($stokvel['id'] ?? 0))) ?>">Appoint from member list</a>
                <?php endif; ?>
            </dd>
        </dl>
        <?php if (!empty($canAppointChairperson) && ($activeMembers ?? []) !== []): ?>
        <form method="post" action="<?= e(url('/platform/tenants/' . (int) ($stokvel['id'] ?? 0) . '/appoint-chairperson')) ?>" class="form-stack mt-4">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="membership_id">Appoint chairperson</label>
                <select id="membership_id" name="membership_id" required>
                    <option value="">Select member…</option>
                    <?php foreach ($activeMembers as $am): ?>
                        <option value="<?= (int) ($am['membership_id'] ?? 0) ?>">
                            <?= e(trim(($am['first_name'] ?? '') . ' ' . ($am['last_name'] ?? '')) ?: ($am['email'] ?? 'Member')) ?>
                            — <?= e($am['email'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" data-confirm="Appoint this member as chairperson?">Appoint</button>
        </form>
        <?php endif; ?>
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
                    <td><?= e($m['role'] ?? '—') ?></td>
                    <td><?= e($m['status'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<section class="card mt-6">
    <h2>Features &amp; governance</h2>
    <p class="text-muted mb-4">
        Type is set by the stokvel in app settings
        (<?= e($stokvelTypes[$stokvel['stokvel_type'] ?? ''] ?? ($stokvel['stokvel_type'] ?? '—')) ?>).
        Manage the type catalogue under <a href="<?= e(url('/platform/stokvel-types')) ?>">Stokvel types</a>.
    </p>
    <?php if (!empty($canManageModules)): ?>
    <form method="post" action="<?= e(url('/platform/tenants/' . (int) ($stokvel['id'] ?? 0) . '/governance')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-grid two-col">
            <label class="checkbox"><input type="checkbox" name="loans_enabled" value="1"<?= !empty($settings['loans_enabled']) ? ' checked' : '' ?>> Loans enabled</label>
            <label class="checkbox"><input type="checkbox" name="require_dual_approval" value="1"<?= !empty($settings['require_dual_approval']) ? ' checked' : '' ?>> Require dual approval (maker-checker)</label>
            <label class="checkbox"><input type="checkbox" name="notification_email_enabled" value="1"<?= ($settings['notification_email_enabled'] ?? 1) ? ' checked' : '' ?>> Email notifications</label>
            <label class="checkbox"><input type="checkbox" name="notification_sms_enabled" value="1"<?= !empty($settings['notification_sms_enabled']) ? ' checked' : '' ?>> SMS notifications</label>
        </div>
        <div class="form-group">
            <label class="form-label" for="payout_mode">Payout mode</label>
            <select id="payout_mode" name="payout_mode">
                <?php foreach (['rotational', 'equal_distribution', 'custom'] as $m): ?>
                    <option value="<?= e($m) ?>"<?= ($settings['payout_mode'] ?? 'rotational') === $m ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $m))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save governance</button>
        </div>
    </form>
    <?php else: ?>
        <dl class="detail-list">
            <dt>Loans</dt><dd><?= !empty($settings['loans_enabled']) ? 'Enabled' : 'Disabled' ?></dd>
            <dt>Dual approval</dt><dd><?= !empty($settings['require_dual_approval']) ? 'Required' : 'Not required' ?></dd>
            <dt>Payout mode</dt><dd><?= e(ucfirst(str_replace('_', ' ', (string) ($settings['payout_mode'] ?? 'rotational')))) ?></dd>
        </dl>
        <p class="text-muted mt-4">Only platform super administrators can change governance.</p>
    <?php endif; ?>
</section>

<section class="card mt-6">
    <h2>Enabled modules</h2>
    <p class="text-muted mb-4">Toggle feature modules for this stokvel. Disabled modules hide nav links and deny related permissions. Loans also syncs the <code>loans_enabled</code> setting.</p>
    <?php if (!empty($canManageModules)): ?>
    <form method="post" action="<?= e(url('/platform/tenants/' . (int) ($stokvel['id'] ?? 0) . '/modules')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-grid two-col">
            <?php foreach (($moduleCatalog ?? []) as $key => $meta): ?>
                <label class="checkbox">
                    <input type="checkbox"
                           name="modules[<?= e($key) ?>]"
                           value="1"
                        <?= !empty($enabledModules[$key]) ? ' checked' : '' ?>>
                    <?= e($meta['label'] ?? ucfirst($key)) ?>
                    <span class="text-muted">(<?= e($key) ?>)</span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save modules</button>
            <a class="btn btn-secondary" href="<?= e(url('/platform/tenants')) ?>">Back to stokvels</a>
        </div>
    </form>
    <?php else: ?>
        <ul class="detail-list">
            <?php foreach (($moduleCatalog ?? []) as $key => $meta): ?>
                <li>
                    <strong><?= e($meta['label'] ?? ucfirst($key)) ?></strong>
                    — <?= !empty($enabledModules[$key]) ? 'enabled' : 'disabled' ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="text-muted mt-4">Only platform super administrators can change modules.</p>
        <a class="btn btn-secondary" href="<?= e(url('/platform/tenants')) ?>">Back to stokvels</a>
    <?php endif; ?>
</section>
