<?php
$settings = $settings ?? [];
$stokvel = $stokvel ?? [];
$brandPrimary = $settings['branding_primary_colour']
    ?? $stokvel['primary_colour']
    ?? $stokvel['brand_color']
    ?? '#1a3d2b';
if (!is_string($brandPrimary) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $brandPrimary)) {
    $brandPrimary = '#1a3d2b';
}
$brandDark = $stokvel['primary_colour_dark'] ?? null;
if (!is_string($brandDark) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $brandDark)) {
    // Darken brand for hover / gradients when no explicit dark colour is set.
    $r = hexdec(substr($brandPrimary, 1, 2));
    $g = hexdec(substr($brandPrimary, 3, 2));
    $b = hexdec(substr($brandPrimary, 5, 2));
    $brandDark = sprintf('#%02x%02x%02x', (int) max(0, $r * 0.82), (int) max(0, $g * 0.82), (int) max(0, $b * 0.82));
}
$brandThemeStyle = sprintf(
    '--brand-primary: %1$s; --brand-primary-dark: %2$s; --color-primary: %1$s; --color-primary-hover: %2$s; --color-sidebar: color-mix(in srgb, %1$s 88%%, #000);',
    $brandPrimary,
    $brandDark
);
$userName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$memberships = $memberships ?? [];
$unreadNotifications = (int) ($unreadNotifications ?? 0);
$navCtx = $navContext ?? 'app';
$topbarAlertsUrl = match ($navCtx) {
    'member' => '/member/notifications',
    'platform' => '/platform/jobs',
    default => '/app/notifications',
};
$topbarProfileUrl = $navCtx === 'platform' ? '/platform/profile' : '/member/profile';
?>
<!DOCTYPE html>
<html lang="en-ZA" data-stokvel-theme style="<?= e($brandThemeStyle) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e(($title ?? 'Dashboard') . ' · ' . ($stokvel['name'] ?? ($navCtx === 'platform' ? 'stockvel Platform' : 'stockvel'))) ?></title>
    <?= view('partials/styles', [], null) ?>
</head>
<body class="app-layout">
<a class="skip-link" href="#main-content">Skip to content</a>

<button type="button" class="drawer-toggle no-desktop" aria-controls="app-drawer" aria-expanded="false" data-drawer-toggle>
    Menu
</button>

<div class="app-shell">
    <aside class="sidebar desktop-only" aria-label="Sidebar">
        <?= view('partials/nav', get_defined_vars(), null) ?>
    </aside>

    <div id="app-drawer" class="drawer" hidden>
        <div class="drawer-panel" role="dialog" aria-modal="true" aria-label="Navigation menu">
            <?= view('partials/nav', get_defined_vars(), null) ?>
        </div>
    </div>

    <div class="app-main">
        <header class="app-topbar">
            <div class="topbar-inner">
                <div>
                    <?php if (!empty($breadcrumbs)): ?>
                        <?= view('partials/breadcrumbs', get_defined_vars(), null) ?>
                    <?php endif; ?>
                    <h1 class="page-title"><?= e($title ?? '') ?></h1>
                </div>
                <div class="topbar-actions">
                    <?php if (!empty($memberships) && count($memberships) > 1): ?>
                        <form method="post" action="<?= e(url('/app/switch-stokvel')) ?>" class="stokvel-switcher">
                            <?= csrf_field() ?>
                            <label class="sr-only" for="switch-stokvel">Switch stokvel</label>
                            <select id="switch-stokvel" name="stokvel_id" onchange="this.form.submit()">
                                <?php foreach ($memberships as $m): ?>
                                    <option value="<?= (int) $m['stokvel_id'] ?>" <?= ((int) ($stokvel['id'] ?? 0) === (int) $m['stokvel_id']) ? 'selected' : '' ?>>
                                        <?= e($m['stokvel_name'] ?? ('Stokvel #' . $m['stokvel_id'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>

                    <a class="btn btn-secondary btn-sm alert-bell" href="<?= e(url($topbarAlertsUrl)) ?>" aria-label="Notifications<?= $unreadNotifications > 0 ? ' (' . $unreadNotifications . ' unread)' : '' ?>">
                        <span class="bell-icon" aria-hidden="true"></span>
                        <span class="desktop-only">Alerts</span>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="badge badge-warning"><?= (int) $unreadNotifications ?></span>
                        <?php endif; ?>
                    </a>

                    <div class="dropdown" data-dropdown>
                        <button type="button" class="btn btn-secondary btn-sm" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">
                            <span class="avatar-initials avatar-sm" aria-hidden="true"><?= e(initials($userName)) ?></span>
                            <span class="desktop-only"><?= e($userName !== '' ? $userName : 'Account') ?></span>
                        </button>
                        <div class="dropdown-menu" role="menu">
                            <a href="<?= e(url($topbarProfileUrl)) ?>" role="menuitem">Profile</a>
                            <?php if ($navCtx === 'platform'): ?>
                                <a href="<?= e(url('/platform/security')) ?>" role="menuitem">Security &amp; MFA</a>
                            <?php else: ?>
                                <a href="<?= e(url('/member/security')) ?>" role="menuitem">Security &amp; MFA</a>
                                <?php if ($navCtx !== 'member'): ?>
                                    <a href="<?= e(url('/app/settings')) ?>" role="menuitem">Settings</a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/logout')) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" role="menuitem">Sign out</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main id="main-content" class="app-content" tabindex="-1">
            <?= view('partials/flash', [], null) ?>
            <?= $content ?>
        </main>
    </div>
</div>

<div class="toast-stack" data-toast-stack aria-live="polite" aria-atomic="true"></div>
<div id="confirm-modal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="confirm-title" hidden>
    <div class="modal">
        <div class="modal-header">
            <h2 id="confirm-title">Confirm</h2>
            <button type="button" class="btn btn-ghost btn-sm" data-modal-close>&times;</button>
        </div>
        <p id="confirm-message">Are you sure?</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
            <button type="button" class="btn btn-danger" id="confirm-accept">Confirm</button>
        </div>
    </div>
</div>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
