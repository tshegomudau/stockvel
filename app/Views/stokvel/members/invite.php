<div class="card">
    <h1>Invite member</h1>
    <form method="post" action="<?= e(url('/app/members/invite')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= e((string) old('email')) ?>">
        </div>
        <div class="form-group">
            <label for="mobile">Mobile (optional)</label>
            <input type="tel" id="mobile" name="mobile" value="<?= e((string) old('mobile')) ?>">
        </div>
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" required>
                <?php foreach (['ordinary', 'treasurer', 'secretary', 'committee', 'chairperson', 'auditor'] as $role): ?>
                    <option value="<?= e($role) ?>"<?= old('role', 'ordinary') === $role ? ' selected' : '' ?>><?= e(ucfirst($role)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Send invitation</button>
    </form>
</div>
