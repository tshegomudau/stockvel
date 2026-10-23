<?php
$navContext = $navContext ?? 'app';
$activeNav = $activeNav ?? '';
$stokvelName = $stokvel['name'] ?? ($navContext === 'platform' ? 'stockvel Platform' : 'Stokvel');
$userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$loansEnabled = function_exists('module_enabled')
    ? module_enabled('loans')
    : (!empty($stokvel['loans_enabled']) || !empty($settings['loans_enabled']));

$appLinks = [
    'dashboard' => ['label' => 'Dashboard', 'url' => route('app.dashboard')],
    'members' => ['label' => 'Members', 'url' => route('app.members'), 'permission' => 'members.view'],
    'beneficiaries' => ['label' => 'Beneficiaries', 'url' => route('app.beneficiaries'), 'permission' => 'beneficiaries.view'],
    'contributions' => ['label' => 'Contributions', 'url' => route('app.contributions'), 'permission' => 'contributions.view'],
    'fees' => ['label' => 'Fees & charges', 'url' => route('app.fees'), 'permission' => 'fees.manage'],
    'expenses' => ['label' => 'Expenses', 'url' => route('app.expenses'), 'permission' => 'expenses.manage', 'module' => 'expenses'],
    'loans' => ['label' => 'Loans', 'url' => route('app.loans'), 'permission' => 'loans.manage', 'module' => 'loans'],
    'loan-products' => ['label' => 'Loan products', 'url' => route('app.loan_products'), 'permission' => 'loans.manage', 'module' => 'loans'],
    'payouts' => ['label' => 'Payouts', 'url' => route('app.payouts'), 'permission' => 'payouts.manage', 'module' => 'payouts'],
    'burial' => ['label' => 'Burial claims', 'url' => url('/app/burial-claims'), 'permission' => 'burial.manage', 'module' => 'burial'],
    'grocery' => ['label' => 'Grocery cycles', 'url' => url('/app/grocery'), 'permission' => 'grocery.manage', 'module' => 'grocery'],
    'meetings' => ['label' => 'Meetings', 'url' => route('app.meetings'), 'permission' => 'meetings.manage', 'module' => 'meetings'],
    'rules' => ['label' => 'Rules', 'url' => route('app.rules'), 'permission' => 'rules.manage', 'module' => 'rules'],
    'documents' => ['label' => 'Documents', 'url' => route('app.documents'), 'permission' => 'documents.view', 'module' => 'documents'],
    'reports' => ['label' => 'Reports', 'url' => route('app.reports'), 'permission' => 'reports.view', 'module' => 'reports'],
    'approvals' => ['label' => 'Approvals', 'url' => route('app.approvals'), 'permission' => 'approvals.view', 'module' => 'approvals'],
    'notifications' => ['label' => 'Notifications', 'url' => route('app.notifications')],
    'support' => ['label' => 'Support / bugs', 'url' => url('/app/support'), 'module' => 'support'],
    'settings' => ['label' => 'Settings', 'url' => route('app.settings'), 'permission' => 'settings.manage'],
    'roles' => ['label' => 'Roles & permissions', 'url' => route('app.settings.roles'), 'permission' => 'settings.manage'],
    'categories' => ['label' => 'Categories', 'url' => route('app.settings.categories'), 'permission' => 'settings.manage'],
];

$memberLinks = [
    'member-dashboard' => ['label' => 'Dashboard', 'url' => route('member.dashboard')],
    'member-statement' => ['label' => 'Statement', 'url' => route('member.statement')],
    'member-beneficiaries' => ['label' => 'Beneficiaries', 'url' => url('/member/beneficiaries')],
    'member-invite' => ['label' => 'Invite member', 'url' => url('/member/invite'), 'permission' => 'members.invite'],
    'member-loans' => ['label' => 'Apply for loan', 'url' => url('/member/loans/apply'), 'module' => 'loans', 'show' => $loansEnabled],
    'member-meetings' => ['label' => 'Meetings', 'url' => url('/member/meetings'), 'module' => 'meetings'],
    'member-documents' => ['label' => 'Documents', 'url' => url('/member/documents'), 'module' => 'documents'],
    'member-change-requests' => ['label' => 'Change requests', 'url' => url('/member/change-requests')],
    'member-support' => ['label' => 'Support / log bug', 'url' => url('/member/support')],
    'member-notifications' => ['label' => 'Notifications', 'url' => url('/member/notifications')],
    'member-profile' => ['label' => 'Profile', 'url' => route('member.profile')],
];

$platformLinks = [
    'platform-dashboard' => ['label' => 'Overview', 'url' => route('platform.dashboard')],
    'platform-tenants' => ['label' => 'Stokvels', 'url' => route('platform.tenants')],
    'platform-users' => ['label' => 'Chairpersons', 'url' => route('platform.users')],
    'platform-roles' => ['label' => 'Roles', 'url' => route('platform.roles')],
    'platform-stokvel-types' => ['label' => 'Stokvel types', 'url' => route('platform.stokvel-types')],
    'platform-support' => ['label' => 'Support tickets', 'url' => route('platform.support')],
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
            <?php
            if (array_key_exists('show', $link) && !$link['show']) {
                continue;
            }
            if (!empty($link['module']) && function_exists('module_enabled') && !module_enabled((string) $link['module'])) {
                continue;
            }
            if (!empty($link['permission']) && !can((string) $link['permission'])) {
                continue;
            }
            ?>
            <li>
                <a class="nav-link<?= $activeNav === $key ? ' is-active' : '' ?>" href="<?= e($link['url']) ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>>
                    <?= e($link['label']) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="sidebar-footer">
        <?php if ($navContext === 'platform'): ?>
            <a class="nav-link<?= $activeNav === 'platform-profile' ? ' is-active' : '' ?>" href="<?= e(route('platform.profile')) ?>">Profile</a>
            <a class="nav-link<?= $activeNav === 'platform-security' ? ' is-active' : '' ?>" href="<?= e(route('platform.security')) ?>">Security</a>
        <?php elseif ($navContext === 'app'): ?>
            <a class="nav-link" href="<?= e(route('member.dashboard')) ?>">Member portal</a>
        <?php elseif ($navContext === 'member'): ?>
            <?php if (can('members.view') || can('settings.manage') || can('approvals.view')): ?>
                <a class="nav-link" href="<?= e(route('app.dashboard')) ?>">Admin area</a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($navContext !== 'platform' && !empty($user['platform_role'])): ?>
            <a class="nav-link" href="<?= e(route('platform.dashboard')) ?>">Platform</a>
        <?php endif; ?>
        <p class="user-chip"><?= e($userName !== '' ? $userName : 'Signed in') ?></p>
        <form method="post" action="<?= e(route('logout')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
        </form>
    </div>
</nav>
