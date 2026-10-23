<?= view('partials/page-header', [
    'title' => 'Create member',
    'description' => 'Add a member directly (useful when migrating from manual records). They can reset password via forgot-password.',
], null) ?>

<form method="post" action="<?= e(url('/app/members')) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label required" for="first_name">First name</label>
            <input id="first_name" name="first_name" required value="<?= e((string) old('first_name')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label required" for="last_name">Last name</label>
            <input id="last_name" name="last_name" required value="<?= e((string) old('last_name')) ?>">
        </div>
    </div>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label required" for="email">Email</label>
            <input id="email" type="email" name="email" required value="<?= e((string) old('email')) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="mobile">Mobile</label>
            <input id="mobile" name="mobile" value="<?= e((string) old('mobile')) ?>">
        </div>
    </div>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="role_code">Role</label>
            <select id="role_code" name="role_code">
                <?php foreach (($roles ?? []) as $role): ?>
                    <option value="<?= e((string) ($role['code'] ?? '')) ?>"<?= old('role_code', 'ordinary_member') === ($role['code'] ?? '') ? ' selected' : '' ?>>
                        <?= e((string) ($role['name'] ?? $role['code'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="joined_at">Joined date</label>
            <input id="joined_at" type="date" name="joined_at" value="<?= e((string) old('joined_at', date('Y-m-d'))) ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="minimum_contribution">Minimum contribution for this member (ZAR)</label>
        <input id="minimum_contribution" name="minimum_contribution" type="number" step="0.01" min="0" value="<?= e((string) old('minimum_contribution')) ?>" placeholder="Stokvel default">
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create member</button>
        <a class="btn btn-secondary" href="<?= e(url('/app/members')) ?>">Cancel</a>
    </div>
</form>
