<?php
$navContext = $navContext ?? 'app';
$activeNav = $activeNav ?? '';
$stokvelName = $stokvel['name'] ?? ($navContext === 'platform' ? 'stockvel Platform' : 'Stokvel');
$userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$loansEnabled = !empty($stokvel['loans_enabled']) || !empty($settings['loans_enabled']) || true;

$appLinks = [
    'dashboard' => ['label' => 'Dashboard', 'url' => route('app.dashboard')],
    'members' => ['label' => 'Members', 'url' => route('app.members')],
    'contributions' => ['label' => 'Contributions', 'url' => route('app.contributions')],
    'fees' => ['label' => 'Fees & charges', 'url' => route('app.fees')],
    'expenses' => ['label' => 'Expenses', 'url' => route('app.expenses')],
    'loans' => ['label' => 'Loans', 'url' => route('app.loans'), 'show' => $loansEnabled],
    'payouts' => ['label' => 'Payouts', 'url' => route('app.payouts')],
    'meetings' => ['label' => 'Meetings', 'url' => route('app.meetings')],
    'rules' => ['label' => 'Rules', 'url' => route('app.rules')],
    'documents' => ['label' => 'Documents', 'url' => route('app.documents')],
    'reports' => ['label' => 'Reports', 'url' => route('app.reports')],
    'approvals' => ['label' => 'Approvals', 'url' => route('app.approvals')],
    'notifications' => ['label' => 'Notifications', 'url' => route('app.notifications')],
    'settings' => ['label' => 'Settings', 'url' => route('app.settings')],
];

$memberLinks = [
    'member-dashboard' => ['label' => 'Dashboard', 'url' => route('member.dashboard')],
    'member-statement' => ['label' => 'Statement', 'url' => route('member.statement')],
    'member-meetings' => ['label' => 'Meetings', 'url' => url('/member/meetings')],
    'member-documents' => ['label' => 'Documents', 'url' => url('/member/documents')],
    'member-notifications' => ['label' => 'Notifications', 'url' => url('/member/notifications')],
    'member-profile' => ['label' => 'Profile', 'url' => route('member.profile')],
];

$platformLinks = [
    'platform-dashboard' => ['label' => 'Overview', 'url' => route('platform.dashboard')],
    'platform-tenants' => ['label' => 'Stokvels', 'url' => route('platform.tenants')],
    'platform-users' => ['label' => 'Users', 'url' => route('platform.users')],
    'platform-audit' => ['label' => 'Audit', 'url' => route('platform.audit')],
    'platform-jobs' => ['label' => 'Failed jobs', 'url' => route('platform.jobs')],
];

$links = match ($navContext) {
    'platform' => $platformLinks,
    'member' => $memberLinks,
    default => $appLinks,
};
?>
<nav class="sidebar-nav" aria-label="Main">
    <div class="sidebar-brand">
        <span class="brand-mark" aria-hidden="true"><?= e(mb_substr($stokvelName, 0, 1)) ?></span>
        <div>
            <strong><?= e($stokvelName) ?></strong>
            <small><?= e($navContext === 'platform' ? 'Platform admin' : ($navContext === 'member' ? 'Member portal' : 'Stokvel admin')) ?></small>
        </div>
    </div>

    <ul class="nav-list">
        <?php foreach ($links as $key => $link): ?>
            <?php if (array_key_exists('show', $link) && !$link['show']) { continue; } ?>
            <li>
                <a class="nav-link<?= $activeNav === $key ? ' is-active' : '' ?>" href="<?= e($link['url']) ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>>
                    <?= e($link['label']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="sidebar-footer">
        <?php if ($navContext === 'app'): ?>
            <a class="nav-link" href="<?= e(route('app.onboarding')) ?>">Setup wizard</a>
            <a class="nav-link" href="<?= e(route('member.dashboard')) ?>">Member portal</a>
        <?php elseif ($navContext === 'member'): ?>
            <a class="nav-link" href="<?= e(route('app.dashboard')) ?>">Admin area</a>
        <?php endif; ?>
        <?php if (!empty($user['platform_role'])): ?>
            <a class="nav-link" href="<?= e(route('platform.dashboard')) ?>">Platform</a>
        <?php endif; ?>
        <p class="user-chip"><?= e($userName !== '' ? $userName : 'Signed in') ?></p>
        <form method="post" action="<?= e(route('logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
        </form>
    </div>
</nav>
