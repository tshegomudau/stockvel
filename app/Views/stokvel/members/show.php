<?php
$memberId = (int) ($member['id'] ?? 0);
$canManage = !empty($canManage);
$beneficiaries = $beneficiaries ?? [];
$pendingChanges = $pendingChanges ?? [];
$defaultMinimumContribution = $defaultMinimumContribution ?? null;
?>
<div class="page-header">
    <h1><?= e(trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''))) ?></h1>
    <div class="toolbar">
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= e(url('/app/members/' . $memberId . '/edit')) ?>">Edit profile</a>
        <?php endif; ?>
        <a class="btn" href="<?= e(url('/app/members/' . $memberId . '/statement')) ?>">Statement</a>
    </div>
</div>

<?php if ($pendingChanges !== []): ?>
<div class="card mb-6">
    <h2>Pending member approval</h2>
    <ul class="plain-list">
        <?php foreach ($pendingChanges as $pc): ?>
            <li><?= e(str_replace('_', ' ', (string) ($pc['change_type'] ?? ''))) ?> — submitted <?= e(sa_datetime($pc['created_at'] ?? '')) ?></li>
        <?php endforeach; ?>
    </ul>
    <p class="form-help">The member must approve these in their portal before they apply.</p>
</div>
<?php endif; ?>

<div class="card mb-6">
    <h2>Contact &amp; membership</h2>
    <dl class="detail-list">
        <dt>Email</dt><dd><?= e($member['email'] ?? '') ?></dd>
        <dt>Mobile</dt><dd><?= e($member['mobile'] ?? '—') ?></dd>
        <dt>ID number</dt><dd><?= e($idMasked ?? '—') ?></dd>
        <dt>Role</dt><dd><?= e($member['role'] ?? '') ?></dd>
        <dt>Status</dt><dd><?= e($member['status'] ?? '') ?></dd>
        <dt>Joined</dt><dd><?= e(sa_date($member['joined_at'] ?? $member['created_at'] ?? '')) ?></dd>
        <dt>Minimum contribution</dt>
        <dd>
            <?php if ($member['minimum_contribution'] !== null && $member['minimum_contribution'] !== ''): ?>
                R <?= e(number_format((float) $member['minimum_contribution'], 2)) ?>
                <span class="text-muted">(member override)</span>
            <?php elseif ($defaultMinimumContribution !== null && $defaultMinimumContribution !== ''): ?>
                R <?= e(number_format((float) $defaultMinimumContribution, 2)) ?>
                <span class="text-muted">(stokvel default)</span>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd>
    </dl>
    <?php if ($canManage): ?>
    <form method="post" action="<?= e(url('/app/members/' . $memberId . '/minimum-contribution')) ?>" class="form-stack mt-4">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="minimum_contribution">Minimum contribution for this member (ZAR)</label>
            <input id="minimum_contribution" name="minimum_contribution" type="number" step="0.01" min="0"
                   value="<?= e((string) ($member['minimum_contribution'] ?? '')) ?>"
                   placeholder="<?= e($defaultMinimumContribution !== null && $defaultMinimumContribution !== '' ? (string) $defaultMinimumContribution : 'Stokvel default') ?>">
            <p class="form-help">Leave blank to use the stokvel default<?= $defaultMinimumContribution !== null && $defaultMinimumContribution !== '' ? ' (R ' . e(number_format((float) $defaultMinimumContribution, 2)) . ')' : '' ?>.</p>
        </div>
        <button type="submit" class="btn btn-secondary">Save minimum contribution</button>
    </form>
    <div class="toolbar mt-4">
        <?php if (($member['status'] ?? '') === 'active'): ?>
            <form method="post" action="<?= e(url('/app/members/' . $memberId . '/suspend')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger" data-confirm="Suspend this member?">Suspend</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('/app/members/' . $memberId . '/reinstate')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Reinstate</button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/app/members/' . $memberId . '/resign')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn" data-confirm="Mark member as resigned?">Mark resigned</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="card mb-6">
    <h2>Beneficiaries</h2>
    <?php if ($beneficiaries === []): ?>
        <p class="text-muted">No beneficiaries on file.</p>
    <?php else: ?>
        <?php foreach ($beneficiaries as $b): ?>
            <?php $bid = (int) ($b['id'] ?? 0); ?>
            <details class="mb-4">
                <summary>
                    <?= e($b['full_name'] ?? '') ?>
                    <?= !empty($b['is_primary']) ? ' (primary)' : '' ?>
                    — <?= e((string) ($b['share_percent'] ?? '')) ?>%
                </summary>
                <?php if ($canManage): ?>
                <form method="post" action="<?= e(url('/app/members/' . $memberId . '/beneficiaries/' . $bid)) ?>" class="form-stack mt-4">
                    <?= csrf_field() ?>
                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label">Full name</label>
                            <input name="full_name" required value="<?= e((string) ($b['full_name'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Relationship</label>
                            <input name="relationship" required value="<?= e((string) ($b['relationship'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label">ID number</label>
                            <input name="id_number" value="<?= e((string) ($b['id_number'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Share %</label>
                            <input name="share_percent" type="number" step="0.01" min="0" max="100" value="<?= e((string) ($b['share_percent'] ?? '100')) ?>">
                        </div>
                    </div>
                    <div class="form-grid two-col">
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input name="phone" value="<?= e((string) ($b['phone'] ?? '')) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input name="email" type="email" value="<?= e((string) ($b['email'] ?? '')) ?>">
                        </div>
                    </div>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="is_primary" value="1" <?= !empty($b['is_primary']) ? 'checked' : '' ?>> Primary beneficiary
                    </label>
                    <div class="toolbar mt-4">
                        <button type="submit" class="btn btn-primary">Submit update for approval</button>
                    </div>
                </form>
                <form method="post" action="<?= e(url('/app/members/' . $memberId . '/beneficiaries/' . $bid . '/delete')) ?>" class="mt-2">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" data-confirm="Submit removal for member approval?">Remove (pending approval)</button>
                </form>
                <?php else: ?>
                <dl class="detail-list mt-4">
                    <dt>Relationship</dt><dd><?= e($b['relationship'] ?? '') ?></dd>
                    <dt>Share</dt><dd><?= e((string) ($b['share_percent'] ?? '')) ?>%</dd>
                    <dt>Phone</dt><dd><?= e($b['phone'] ?? '—') ?></dd>
                    <dt>Email</dt><dd><?= e($b['email'] ?? '—') ?></dd>
                </dl>
                <?php endif; ?>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <h3 class="mt-6">Add beneficiary</h3>
    <form method="post" action="<?= e(url('/app/members/' . $memberId . '/beneficiaries')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-grid two-col">
            <div class="form-group">
                <label class="form-label" for="new_full_name">Full name</label>
                <input id="new_full_name" name="full_name" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="new_relationship">Relationship</label>
                <input id="new_relationship" name="relationship" required>
            </div>
        </div>
        <div class="form-grid two-col">
            <div class="form-group">
                <label class="form-label" for="new_id_number">ID number</label>
                <input id="new_id_number" name="id_number">
            </div>
            <div class="form-group">
                <label class="form-label" for="new_share">Share %</label>
                <input id="new_share" name="share_percent" type="number" step="0.01" min="0" max="100" value="100">
            </div>
        </div>
        <div class="form-grid two-col">
            <div class="form-group">
                <label class="form-label" for="new_phone">Phone</label>
                <input id="new_phone" name="phone">
            </div>
            <div class="form-group">
                <label class="form-label" for="new_email">Email</label>
                <input id="new_email" name="email" type="email">
            </div>
        </div>
        <label class="checkbox-inline">
            <input type="checkbox" name="is_primary" value="1"> Primary beneficiary
        </label>
        <button type="submit" class="btn btn-primary mt-4">Submit for member approval</button>
    </form>
    <?php endif; ?>
</div>

<?php if ($canManage && !empty($permissionOptions)): ?>
<div class="card">
    <h2>Extra permissions</h2>
    <p class="form-help mb-4">Grant or revoke app permissions beyond this member&apos;s role. Changes apply immediately.</p>
    <form method="post" action="<?= e(url('/app/members/' . $memberId . '/permissions')) ?>">
        <?= csrf_field() ?>
        <ul class="checkbox-list">
            <?php foreach ($permissionOptions as $opt): ?>
                <?php $code = (string) ($opt['code'] ?? ''); ?>
                <li>
                    <label class="checkbox-inline">
                        <input type="checkbox" name="permissions[]" value="<?= e($code) ?>"
                            <?= in_array($code, $grantedPermissionCodes ?? [], true) ? 'checked' : '' ?>>
                        <?= e((string) ($opt['label'] ?? $code)) ?>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
        <button type="submit" class="btn btn-primary mt-4">Save permissions</button>
    </form>
</div>
<?php endif; ?>
