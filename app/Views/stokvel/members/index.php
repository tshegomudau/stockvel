<?= view('partials/page-header', [
    'title' => 'Members',
    'description' => 'Invite, approve, and manage memberships for this stokvel.',
    'actionUrl' => url('/app/members/invite'),
    'actionLabel' => 'Invite member',
], null) ?>

<form method="get" class="filter-bar" role="search">
    <div class="form-group">
        <label for="member-search">Search</label>
        <input type="search" id="member-search" name="q" value="<?= e((string) ($q ?? '')) ?>" placeholder="Name or email" data-table-filter="members-table">
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
        <button type="submit" class="btn btn-secondary">Apply filters</button>
    </div>
</form>

<?php if (empty($paginator->items)): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No members found',
        'emptyMessage' => 'Invite committee members and ordinary members to get started.',
        'emptyActionUrl' => url('/app/members/invite'),
        'emptyActionLabel' => 'Invite member',
    ], null) ?>
<?php else: ?>
    <div class="table-meta">
        <span><?= (int) $paginator->total ?> member<?= (int) $paginator->total === 1 ? '' : 's' ?></span>
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
                $status = (string) ($row['status'] ?? '');
                $badge = match ($status) {
                    'active' => 'badge-success',
                    'suspended', 'terminated' => 'badge-danger',
                    'invited', 'pending' => 'badge-warning',
                    default => '',
                };
                ?>
                <tr>
                    <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
                    <td><?= e($row['email'] ?? '') ?></td>
                    <td><?= e((string) ($row['member_number'] ?? '—')) ?></td>
                    <td><span class="badge <?= e($badge) ?>"><span class="status-dot <?= e($status === 'active' ? 'success' : ($status === 'suspended' ? 'danger' : 'warning')) ?>"></span><?= e(ucfirst($status)) ?></span></td>
                    <td class="actions">
                        <a class="btn btn-sm btn-secondary" href="<?= e(url('/app/members/' . ($row['id'] ?? ''))) ?>">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= view('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
