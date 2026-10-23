<?= view('partials/page-header', [
    'title' => 'Roles & permissions',
    'description' => 'Manage committee roles, permission sets, and who holds each role.',
], null) ?>
<p class="text-muted mb-4"><a href="<?= e(url('/app/settings')) ?>">&larr; Back to settings</a></p>

<?php if (empty($roles)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No roles available',
        'emptyMessage' => 'Stokvel roles could not be loaded.',
    ], null) ?>
<?php else: ?>
<form class="filter-bar" role="search" onsubmit="return false;">
    <div class="form-group">
        <label for="role-search">Search</label>
        <input type="search" id="role-search" placeholder="Role name or code" data-table-filter="roles-table">
    </div>
</form>
<div class="table-wrap">
    <table class="table" id="roles-table">
        <thead>
            <tr>
                <th>Role</th>
                <th>Code</th>
                <th>Members</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $role): ?>
                <tr>
                    <td><?= e($role['name'] ?? '') ?></td>
                    <td><code><?= e($role['code'] ?? '') ?></code></td>
                    <td><?= (int) ($role['member_count'] ?? 0) ?></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-secondary" href="<?= e(url('/app/settings/roles/' . rawurlencode((string) ($role['code'] ?? '')))) ?>">
                            Manage
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
