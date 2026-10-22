<div class="auth-card">
    <h1>Sign out</h1>
    <p class="text-muted">Confirm you want to end this session.</p>
    <form method="post" action="<?= e(url('/logout')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Sign out</button>
            <a class="btn btn-secondary" href="<?= e(url('/dashboard')) ?>">Cancel</a>
        </div>
    </form>
</div>
