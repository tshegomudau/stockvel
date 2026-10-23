<?= view('partials/page-header', [
    'title' => 'Invite member',
    'description' => !empty($canAssignRoles)
        ? 'Send an invitation. After they accept, an admin must approve before they become active.'
        : 'Invite someone as an ordinary member. An admin must approve them after they accept.',
], null) ?>
<div class="card">
    <form method="post" action="<?= e(url(($inviteAction ?? '/app/members/invite'))) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?= e((string) old('email')) ?>">
        </div>
        <div class="form-group">
            <label for="mobile">Mobile (optional)</label>
            <input type="tel" id="mobile" name="mobile" value="<?= e((string) old('mobile')) ?>">
        </div>
        <?php if (!empty($canAssignRoles)): ?>
        <div class="form-group">
            <label for="role">Role</label>
            <select id="role" name="role" required>
                <?php foreach (['ordinary', 'treasurer', 'secretary', 'committee', 'chairperson', 'auditor'] as $role): ?>
                    <option value="<?= e($role) ?>"<?= old('role', 'ordinary') === $role ? ' selected' : '' ?>><?= e(ucfirst($role)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
            <input type="hidden" name="role" value="ordinary">
            <p class="form-help">Invites from members are for ordinary membership only.</p>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Send invitation</button>
    </form>
</div>
