<?php
use App\Core\Session;

$status = (int) ($status ?? $code ?? 500);
$isAuth = !empty($user['id']) || Session::get('user_id') !== null;
$dashboardUrl = $dashboardUrl ?? ($isAuth ? url('/app') : url('/login'));
$safeBackUrl = $safeBackUrl ?? $dashboardUrl;
$heading = $heading ?? match ($status) {
    401 => 'Sign in required',
    403 => 'Access denied',
    404 => 'Page not found',
    419 => 'Session expired',
    422 => 'Invalid request',
    429 => 'Too many requests',
    503 => 'Service unavailable',
    500 => 'Something went wrong',
    default => 'Error ' . $status,
};
$safeMessage = (string) ($message ?? 'Please try again or return to the dashboard.');
$dashJs = json_encode($dashboardUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$backJs = json_encode($safeBackUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<div class="card error-card">
    <p class="text-muted mb-2">Error <?= (int) $status ?></p>
    <h1><?= e($heading) ?></h1>
    <p><?= e($safeMessage) ?></p>
    <div class="form-actions mt-6">
        <?php if ($isAuth): ?>
            <a class="btn btn-primary" href="<?= e($dashboardUrl) ?>">Back to Dashboard</a>
            <?php if ($status === 419): ?>
            <form method="post" action="<?= e(url('/logout')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Sign out</button>
            </form>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary" id="error-go-back">Go Back</button>
            <script>
            (function () {
                var dash = <?= $dashJs ?: '""' ?>;
                var safe = <?= $backJs ?: '""' ?>;
                var btn = document.getElementById('error-go-back');
                if (!btn) return;
                btn.addEventListener('click', function () {
                    if (safe && safe !== dash) {
                        window.location.href = safe;
                        return;
                    }
                    if (window.history.length > 1) {
                        window.history.back();
                        return;
                    }
                    window.location.href = dash;
                });
            })();
            </script>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= e(url('/login')) ?>">Sign in</a>
            <a class="btn btn-secondary" href="<?= e(url('/')) ?>">Home</a>
        <?php endif; ?>
    </div>
</div>
