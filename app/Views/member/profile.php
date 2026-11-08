<?= view('partials/page-header', [
    'title' => 'My profile',
    'description' => 'Update your contact details, address, emergency contact, and password. ID numbers are stored encrypted.',
], null) ?>
<?php if (!empty($completeness)): ?>
<section class="card mb-4" aria-label="Profile completeness">
    <h2 class="h3">Profile completion: <?= (int) $completeness['percent'] ?>%</h2>
    <?php if (!empty($completeness['missing'])): ?>
        <p>Profile incomplete:</p>
        <ul>
            <?php foreach ($completeness['missing'] as $item): ?>
                <li><?= e($item) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>All recommended profile items are complete.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
<p class="mb-4 page-actions">
    <a class="btn btn-secondary" href="<?= e(url('/member/security')) ?>">Security &amp; MFA</a>
    <a class="btn btn-secondary" href="<?= e(url('/member/beneficiaries')) ?>">My beneficiaries</a>
</p>
<form method="post" action="<?= e(url('/member/profile')) ?>" class="form-stack card mb-6">
<?= csrf_field() ?>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="first_name">First name</label>
<input class="form-control" type="text" id="first_name" name="first_name" required value="<?= e($profile['first_name'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="last_name">Last name</label>
<input class="form-control" type="text" id="last_name" name="last_name" required value="<?= e($profile['last_name'] ?? '') ?>"></div>
</div>
<div class="form-group"><label class="form-label" for="mobile">Mobile</label>
<input class="form-control" type="text" id="mobile" name="mobile" value="<?= e($profile['mobile'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="id_number">SA ID number</label>
<input class="form-control" type="text" id="id_number" name="id_number" value="" placeholder="<?= e($idMasked ?? '••••') ?>" autocomplete="off">
<p class="form-help">Leave blank to keep the current encrypted value.</p></div>
<p class="text-muted">Email: <?= e($profile['email'] ?? '') ?></p>

<h2 class="h3">Residential address</h2>
<div class="form-group"><label class="form-label" for="address_line1">Street address</label>
<input class="form-control" type="text" id="address_line1" name="address_line1" value="<?= e($profile['address_line1'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="address_line2">Address line 2</label>
<input class="form-control" type="text" id="address_line2" name="address_line2" value="<?= e($profile['address_line2'] ?? '') ?>"></div>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="suburb">Suburb</label>
<input class="form-control" type="text" id="suburb" name="suburb" value="<?= e($profile['suburb'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="city">City / town</label>
<input class="form-control" type="text" id="city" name="city" value="<?= e($profile['city'] ?? '') ?>"></div>
</div>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="province">Province</label>
<input class="form-control" type="text" id="province" name="province" value="<?= e($profile['province'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="postal_code">Postal code</label>
<input class="form-control" type="text" id="postal_code" name="postal_code" value="<?= e($profile['postal_code'] ?? '') ?>"></div>
</div>

<h2 class="h3">Emergency contact</h2>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="emergency_contact_name">Full name</label>
<input class="form-control" type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?= e($profile['emergency_contact_name'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="emergency_contact_phone">Phone</label>
<input class="form-control" type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= e($profile['emergency_contact_phone'] ?? '') ?>"></div>
</div>

<button class="btn btn-primary" type="submit">Save profile</button>
</form>

<section class="card mb-6 form-stack" aria-label="ID document">
    <h2 class="h3">ID document on file</h2>
    <?php if (!empty($idDocuments)): ?>
        <ul>
            <?php foreach ($idDocuments as $doc): ?>
                <li>
                    <a href="<?= e(url('/member/documents/' . (int) $doc['id'])) ?>"><?= e($doc['title'] ?? 'Document') ?></a>
                    <?php if (!empty($doc['category_name'])): ?>
                        <span class="text-muted"> · <?= e($doc['category_name']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($doc['created_at'])): ?>
                        <span class="text-muted"> · <?= e(sa_datetime($doc['created_at'])) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="text-muted">No ID / identity document uploaded yet.</p>
    <?php endif; ?>

    <?php if (!empty($uploadsEnabled)): ?>
        <form method="post" action="<?= e(url('/member/profile/id-document')) ?>" enctype="multipart/form-data" class="form-stack">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="title">Title</label>
                <input class="form-control" type="text" id="title" name="title" value="ID document" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="id_document">File (PDF or image)</label>
                <input class="form-control" type="file" id="id_document" name="id_document" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
            </div>
            <button type="submit" class="btn btn-secondary">Upload ID document</button>
        </form>
    <?php else: ?>
        <p class="form-help">File uploads are currently disabled by the platform admin. Enable them under Platform → Features &amp; storage.</p>
    <?php endif; ?>
</section>

<form method="post" action="<?= e(url('/member/profile/password')) ?>" class="form-stack card">
<?= csrf_field() ?>
<h2>Change password</h2>
<div class="form-group"><label class="form-label" for="current_password">Current password</label>
<input class="form-control" type="password" id="current_password" name="current_password" required autocomplete="current-password"></div>
<?= view('partials/password-field', [
    'id' => 'password',
    'name' => 'password',
    'label' => 'New password',
    'passwordPolicy' => $passwordPolicy ?? null,
], null) ?>
<div class="form-group"><label class="form-label" for="password_confirmation">Confirm new password</label>
<input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required minlength="<?= (int) (($passwordPolicy['min_length'] ?? 8)) ?>" autocomplete="new-password"></div>
<button class="btn btn-primary" type="submit">Update password</button>
</form>
