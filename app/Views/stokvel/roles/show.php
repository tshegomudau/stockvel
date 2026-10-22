<?= view('partials/page-header', [
    'title' => ($role['name'] ?? 'Role') . ' — permissions',
    'description' => 'Edit allowed permissions and link members to this role.',
], null) ?>
<p class="text-muted mb-4"><a href="<?= e(url('/app/settings/roles')) ?>">&larr; All roles</a></p>

<section class="form-section mb-6">
    <h2>Permissions</h2>
    <?php if (!empty($role['is_full_access'])): ?>
        <p class="text-muted">This role has full access and cannot be restricted.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/app/settings/roles/' . rawurlencode((string) ($role['code'] ?? '')))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Module / group</th>
                        <th>Permission</th>
                        <th>Allowed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($permissionGroups ?? []) as $group => $perms): ?>
                        <?php foreach ($perms as $i => $perm): ?>
                            <tr>
                                <?php if ($i === 0): ?>
                                    <td rowspan="<?= count($perms) ?>"><strong><?= e(ucfirst((string) $group)) ?></strong></td>
                                <?php endif; ?>
                                <td>
                                    <label for="perm_<?= e(str_replace('.', '_', $perm)) ?>">
                                        <?= e(ucwords(str_replace(['.', '_'], [' — ', ' '], $perm))) ?>
                                    </label>
                                    <div class="text-muted"><code><?= e($perm) ?></code></div>
                                </td>
                                <td>
                                    <input type="checkbox"
                                           id="perm_<?= e(str_replace('.', '_', $perm)) ?>"
                                           name="permissions[]"
                                           value="<?= e($perm) ?>"
                                        <?= in_array($perm, $role['permissions'] ?? [], true) ? ' checked' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save permissions</button>
        </div>
    </form>
    <?php endif; ?>
</section>

<section class="form-section mb-6">
    <h2>Members with this role</h2>
    <?php if (empty($members)): ?>
        <p class="text-muted">No members currently linked to this role.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Member #</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: '—') ?></td>
                        <td><?= e($m['email'] ?? '') ?></td>
                        <td><?= e($m['member_number'] ?? '') ?></td>
                        <td><?= e($m['status'] ?? '') ?></td>
                        <td class="actions">
                            <form method="post" action="<?= e(url('/app/settings/roles/' . rawurlencode((string) ($role['code'] ?? '')) . '/remove')) ?>" class="inline-form" onsubmit="return confirm('Remove this role from the member?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="membership_id" value="<?= (int) ($m['membership_id'] ?? 0) ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="form-section">
    <h2>Assign member</h2>
    <?php if (empty($assignableMembers)): ?>
        <p class="text-muted">All active members already have this role, or no members are available.</p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/app/settings/roles/' . rawurlencode((string) ($role['code'] ?? '')) . '/assign')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="membership_id">Member</label>
            <select id="membership_id" name="membership_id" required>
                <option value="">Select member…</option>
                <?php foreach ($assignableMembers as $m): ?>
                    <?php
                    $label = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
                    if ($label === '') {
                        $label = (string) ($m['email'] ?? 'Member');
                    }
                    $label .= ' (' . ($m['member_number'] ?? '') . ')';
                    ?>
                    <option value="<?= (int) ($m['membership_id'] ?? 0) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Assign role</button>
    </form>
    <?php endif; ?>
</section>
