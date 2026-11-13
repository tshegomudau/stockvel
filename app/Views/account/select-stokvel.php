<?php
$memberships = is_array($memberships ?? null) ? $memberships : [];
$showPlatform = !empty($showPlatformOption) || (function_exists('user_is_platform_admin') && user_is_platform_admin(is_array($user ?? null) ? $user : null));
?>
<div class="auth-shell">
    <div class="auth-card card stokvel-picker-card">
        <p class="brand-kicker brand-kicker--compact">
            <span class="brand-mark" aria-hidden="true">S</span>
            <span>stockvel</span>
        </p>
        <h1>Choose a workspace</h1>
        <p class="lead">You are linked to more than one place. Select which one to open for this session.</p>

        <?php if ($memberships === [] && !$showPlatform): ?>
            <?= view('partials/empty', [
                'emptyTitle' => 'No stokvels available',
                'emptyMessage' => 'Ask an admin for an invitation, or create a stokvel request.',
            ], null) ?>
            <a class="btn btn-primary" href="<?= e(url('/account/get-started')) ?>">Get started</a>
        <?php else: ?>
            <ul class="stokvel-picker" role="list">
                <?php if ($showPlatform): ?>
                    <li>
                        <form method="post" action="<?= e(url('/account/select-stokvel')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="stokvel_id" value="platform">
                            <button type="submit" class="stokvel-picker-item">
                                <span class="stokvel-picker-name">Platform console</span>
                                <span class="stokvel-picker-meta">Manage all stokvels</span>
                            </button>
                        </form>
                    </li>
                <?php endif; ?>
                <?php foreach ($memberships as $m): ?>
                    <?php
                    $sid = (int) ($m['stokvel_id'] ?? 0);
                    $name = (string) ($m['stokvel_name'] ?? ('Stokvel #' . $sid));
                    $status = (string) ($m['status'] ?? '');
                    ?>
                    <li>
                        <form method="post" action="<?= e(url('/account/select-stokvel')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="stokvel_id" value="<?= $sid ?>">
                            <button type="submit" class="stokvel-picker-item">
                                <span class="stokvel-picker-name"><?= e($name) ?></span>
                                <span class="stokvel-picker-meta">
                                    <?= e(ucfirst($status !== '' ? $status : 'member')) ?>
                                    <?php if (!empty($m['member_number'])): ?>
                                        · <?= e((string) $m['member_number']) ?>
                                    <?php endif; ?>
                                </span>
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
