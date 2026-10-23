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
            <button type="button" class="btn btn-secondary" id="error-go-back"
                    data-dashboard-url="<?= e($dashboardUrl) ?>"
                    data-safe-back-url="<?= e($safeBackUrl) ?>">Go Back</button>
        <?php else: ?>
            <a class="btn btn-primary" href="<?= e(url('/login')) ?>">Sign in</a>
            <a class="btn btn-secondary" href="<?= e(url('/')) ?>">Home</a>
        <?php endif; ?>
    </div>
</div>
