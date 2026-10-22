<?= view('partials/page-header', [
    'title' => 'My profile',
    'description' => 'Update your contact details and password. ID numbers are stored encrypted.',
], null) ?>
<p class="mb-4"><a class="btn btn-secondary" href="<?= e(url('/member/security')) ?>">Security &amp; MFA</a></p>
<form method="post" action="<?= e(url('/member/profile')) ?>" class="form-stack card mb-6">
<?= csrf_field() ?>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="first_name">First name</label>
<input id="first_name" name="first_name" required value="<?= e($profile['first_name'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="last_name">Last name</label>
<input id="last_name" name="last_name" required value="<?= e($profile['last_name'] ?? '') ?>"></div>
</div>
<div class="form-group"><label class="form-label" for="mobile">Mobile</label>
<input id="mobile" name="mobile" value="<?= e($profile['mobile'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="id_number">SA ID number</label>
<input id="id_number" name="id_number" value="" placeholder="<?= e($idMasked ?? '••••') ?>" autocomplete="off">
<p class="form-help">Leave blank to keep the current encrypted value.</p></div>
<p class="text-muted">Email: <?= e($profile['email'] ?? '') ?></p>
<button class="btn btn-primary" type="submit">Save profile</button>
</form>

<form method="post" action="<?= e(url('/member/profile/password')) ?>" class="form-stack card">
<?= csrf_field() ?>
<h2>Change password</h2>
<div class="form-group"><label class="form-label" for="current_password">Current password</label>
<input type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
<div class="form-group"><label class="form-label" for="password">New password</label>
<input type="password" id="password" name="password" required minlength="8" autocomplete="new-password"></div>
<div class="form-group"><label class="form-label" for="password_confirmation">Confirm new password</label>
<input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
<button class="btn btn-primary" type="submit">Update password</button>
</form>
