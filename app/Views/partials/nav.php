<?php
$navContext = $navContext ?? 'app';
$activeNav = $activeNav ?? '';
$stokvelName = $stokvel['name'] ?? 'Stokvel';
$userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

$appLinks = [
    'dashboard' => ['label' => 'Dashboard', 'url' => url('/app')],
    'members' => ['label' => 'Members', 'url' => url('/app/members')],
    'contributions' => ['label' => 'Contributions', 'url' => url('/app/contributions')],
    'fees' => ['label' => 'Fees', 'url' => url('/app/fees')],
    'expenses' => ['label' => 'Expenses', 'url' => url('/app/expenses')],
    'loans' => ['label' => 'Loans', 'url' => url('/app/loans')],
    'payouts' => ['label' => 'Payouts', 'url' => url('/app/payouts')],
    'meetings' => ['label' => 'Meetings', 'url' => url('/app/meetings')],
    'rules' => ['label' => 'Rules', 'url' => url('/app/rules')],
    'documents' => ['label' => 'Documents', 'url' => url('/app/documents')],
    'reports' => ['label' => 'Reports', 'url' => url('/app/reports')],
    'approvals' => ['label' => 'Approvals', 'url' => url('/app/approvals')],
    'settings' => ['label' => 'Settings', 'url' => url('/app/settings')],
    'notifications' => ['label' => 'Notifications', 'url' => url('/app/notifications')],
];

$memberLinks = [
    'member-dashboard' => ['label' => 'Dashboard', 'url' => url('/member')],
    'member-statement' => ['label' => 'Statement', 'url' => url('/member/statement')],
    'member-meetings' => ['label' => 'Meetings', 'url' => url('/member/meetings')],
    'member-documents' => ['label' => 'Documents', 'url' => url('/member/documents')],
    'member-notifications' => ['label' => 'Notifications', 'url' => url('/member/notifications')],
    'member-profile' => ['label' => 'Profile', 'url' => url('/member/profile')],
];

$platformLinks = [
    'platform-dashboard' => ['label' => 'Overview', 'url' => url('/platform')],
    'platform-tenants' => ['label' => 'Stokvels', 'url' => url('/platform/tenants')],
    'platform-users' => ['label' => 'Users', 'url' => url('/platform/users')],
    'platform-audit' => ['label' => 'Audit', 'url' => url('/platform/audit')],
    'platform-jobs' => ['label' => 'Failed jobs', 'url' => url('/platform/jobs')],
];

$links = match ($navContext) {
    'platform' => $platformLinks,
    'member' => $memberLinks,
    default => $appLinks,
};
?>
<nav class="sidebar-nav" aria-label="Main">
    <div class="sidebar-brand">
        <span class="brand-mark"><?= e(mb_substr($stokvelName, 0, 1)) ?></span>
        <div>
            <strong><?= e($stokvelName) ?></strong>
            <small><?= e($navContext === 'platform' ? 'Platform' : ($navContext === 'member' ? 'Member portal' : 'Admin')) ?></small>
        </div>
    </div>
    <ul class="nav-list">
        <?php foreach ($links as $key => $link): ?>
            <li>
                <a class="nav-link<?= $activeNav === $key ? ' is-active' : '' ?>" href="<?= e($link['url']) ?>">
                    <?= e($link['label']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="sidebar-footer">
        <?php if ($navContext !== 'member'): ?>
            <a class="nav-link" href="<?= e(url('/member')) ?>">Member portal</a>
        <?php else: ?>
            <a class="nav-link" href="<?= e(url('/app')) ?>">Admin area</a>
        <?php endif; ?>
        <?php if (!empty($user['platform_role'])): ?>
            <a class="nav-link" href="<?= e(url('/platform')) ?>">Platform</a>
        <?php endif; ?>
        <p class="user-chip"><?= e($userName ?: 'User') ?></p>
        <form method="post" action="<?= e(url('/logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
        </form>
    </div>
</nav>
