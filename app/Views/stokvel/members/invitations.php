<?= view('partials/page-header', [
    'title' => 'Pending invitations',
    'description' => 'Invited people who have not accepted yet. Approve memberships after they join if your stokvel requires it.',
], null) ?>

<div class="page-actions mb-4">
    <a class="btn btn-secondary" href="<?= e(url('/app/members')) ?>">All members</a>
    <a class="btn btn-secondary" href="<?= e(url('/app/members?status=pending')) ?>">Pending approval</a>
    <?php if (!empty($canInvite)): ?>
        <a class="btn btn-primary" href="<?= e(url('/app/members/invite')) ?>">Invite member</a>
    <?php endif; ?>
</div>

<?php
$pendingInvitations = is_array($pendingInvitations ?? null) ? $pendingInvitations : [];
?>
<?php if ($pendingInvitations === []): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No pending invitations',
        'emptyMessage' => 'Open invites appear here until the person accepts or the invite expires.',
        'emptyActionUrl' => !empty($canInvite) ? url('/app/members/invite') : null,
        'emptyActionLabel' => !empty($canInvite) ? 'Invite member' : null,
    ], null) ?>
<?php else: ?>
    <div class="table-meta">
        <span><?= count($pendingInvitations) ?> pending invitation<?= count($pendingInvitations) === 1 ? '' : 's' ?></span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Sent</th>
                    <th>Expires</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingInvitations as $inv): ?>
                    <tr>
                        <td><?= e((string) ($inv['email'] ?? '—')) ?></td>
                        <td><?= e((string) ($inv['mobile'] ?? '—')) ?></td>
                        <td><?= e(ucfirst(str_replace('_', ' ', (string) ($inv['role_code'] ?? $inv['role_name'] ?? 'member')))) ?></td>
                        <td><span class="badge badge-warning"><?= e((string) ($inv['status'] ?? 'pending')) ?></span></td>
                        <td><?= e(sa_datetime((string) ($inv['created_at'] ?? ''))) ?></td>
                        <td><?= e(sa_datetime((string) ($inv['expires_at'] ?? ''))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
