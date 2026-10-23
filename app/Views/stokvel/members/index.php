<?= view('partials/page-header', [
    'title' => 'Members',
    'description' => 'Invite, create, and manage memberships for this stokvel.',
], null) ?>
<div class="page-actions mb-4">
    <a class="btn btn-primary" href="<?= e(url('/app/members/invite')) ?>">Invite member</a>
    <?php if (!empty($canManage)): ?>
        <a class="btn btn-secondary" href="<?= e(url('/app/members/create')) ?>">Create member</a>
        <a class="btn btn-secondary" href="<?= e(url('/app/members/import')) ?>">Bulk import</a>
    <?php endif; ?>
</div>

<form method="get" action="<?= e(url('/app/members')) ?>" class="filter-bar" role="search">
    <div class="form-group">
        <label for="member-search">Search</label>
        <input type="search" id="member-search" name="q" value="<?= e((string) ($q ?? '')) ?>" placeholder="Name or email">
    </div>
    <div class="form-group">
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (['active', 'invited', 'pending', 'suspended', 'resigned', 'terminated'] as $st): ?>
                <option value="<?= e($st) ?>"<?= ($statusFilter ?? '') === $st ? ' selected' : '' ?>><?= e(ucfirst($st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-secondary">Filter</button>
        <?php if (($q ?? '') !== '' || ($statusFilter ?? '') !== ''): ?>
            <a class="btn btn-ghost" href="<?= e(url('/app/members')) ?>">Clear</a>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($paginator->items)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No members found',
        'emptyMessage' => 'Invite or create members to get started.',
        'emptyActionUrl' => url('/app/members/invite'),
        'emptyActionLabel' => 'Invite member',
    ], null) ?>
<?php else: ?>
    <div class="table-meta">
        <span><?= (int) $paginator->total ?> member<?= (int) $paginator->total === 1 ? '' : 's' ?></span>
        <span class="text-muted">Amber = ≤2 unpaid contributions · Red = more than 2</span>
    </div>
    <div class="table-wrap">
        <table class="table" id="members-table">
            <thead>
            <tr>
                <th scope="col">Name</th>
                <th scope="col">Email</th>
                <th scope="col">Member no.</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($paginator->items as $row): ?>
                <?php
                $mid = (int) ($row['id'] ?? 0);
                $status = (string) ($row['status'] ?? '');
                $badge = match ($status) {
                    'active' => 'badge-success',
                    'suspended', 'terminated' => 'badge-danger',
                    'invited', 'pending' => 'badge-warning',
                    default => '',
                };
                $owing = (int) (($owingByMember ?? [])[$mid] ?? 0);
                $rowClass = '';
                if ($owing > 2) {
                    $rowClass = 'row-owing-high';
                } elseif ($owing >= 1) {
                    $rowClass = 'row-owing-low';
                }
                ?>
                <tr class="<?= e($rowClass) ?>">
                    <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
                    <td><?= e($row['email'] ?? '') ?></td>
                    <td><?= e((string) ($row['member_number'] ?? '—')) ?></td>
                    <td><span class="badge <?= e($badge) ?>"><span class="status-dot <?= e($status === 'active' ? 'success' : ($status === 'suspended' ? 'danger' : 'warning')) ?>"></span><?= e(ucfirst($status)) ?></span></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-secondary" href="<?= e(url('/app/members/' . $mid)) ?>">View</a>
                        <?php if (!empty($canManage)): ?>
                            <?php if (in_array($status, ['pending', 'invited'], true)): ?>
                                <form method="post" action="<?= e(url('/app/members/' . $mid . '/approve')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-primary" data-confirm="Approve this member?">Approve</button>
                                </form>
                            <?php elseif ($status === 'active'): ?>
                                <form method="post" action="<?= e(url('/app/members/' . $mid . '/suspend')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-danger" data-confirm="Suspend this member?">Suspend</button>
                                </form>
                                <form method="post" action="<?= e(url('/app/members/' . $mid . '/resign')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-secondary" data-confirm="Mark member as resigned?">Mark resigned</button>
                                </form>
                            <?php elseif (in_array($status, ['suspended', 'resigned'], true)): ?>
                                <form method="post" action="<?= e(url('/app/members/' . $mid . '/reinstate')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-primary">Reinstate</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
