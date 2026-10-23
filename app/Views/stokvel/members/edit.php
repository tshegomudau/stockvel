<div class="page-header">
    <h1>Edit member</h1>
    <a class="btn" href="<?= e(url('/app/members/' . ($member['id'] ?? ''))) ?>">Back to profile</a>
</div>
<p class="text-muted mb-4">Profile fields are sent to the member for approval. Minimum contribution is saved immediately.</p>
<form method="post" action="<?= e(url('/app/members/' . ($member['id'] ?? ''))) ?>" class="form-stack card">
    <?= csrf_field() ?>
    <div class="form-grid two-col">
        <div class="form-group">
            <label class="form-label" for="first_name">First name</label>
            <input id="first_name" name="first_name" required value="<?= e((string) old('first_name', (string) ($member['first_name'] ?? ''))) ?>">
        </div>
        <div class="form-group">
            <label class="form-label" for="last_name">Last name</label>
            <input id="last_name" name="last_name" required value="<?= e((string) old('last_name', (string) ($member['last_name'] ?? ''))) ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label" for="mobile">Mobile</label>
        <input id="mobile" name="mobile" value="<?= e((string) old('mobile', (string) ($member['mobile'] ?? ''))) ?>">
    </div>
    <div class="form-group">
        <label class="form-label" for="id_number">SA ID number</label>
        <input id="id_number" name="id_number" value="" placeholder="<?= e($idMasked ?? '••••') ?>" autocomplete="off">
        <p class="form-help">Leave blank to keep the current encrypted value. Stored encrypted when the member approves.</p>
    </div>
    <div class="form-group">
        <label class="form-label" for="minimum_contribution">Minimum contribution for this member (ZAR)</label>
        <input id="minimum_contribution" name="minimum_contribution" type="number" step="0.01" min="0"
               value="<?= e((string) old('minimum_contribution', (string) ($member['minimum_contribution'] ?? ''))) ?>"
               placeholder="<?= e(($defaultMinimumContribution ?? null) !== null && $defaultMinimumContribution !== '' ? (string) $defaultMinimumContribution : 'Stokvel default') ?>">
        <p class="form-help">Leave blank to use the stokvel default<?= ($defaultMinimumContribution ?? null) !== null && $defaultMinimumContribution !== '' ? ' (R ' . e(number_format((float) $defaultMinimumContribution, 2)) . ')' : '' ?>. Saved immediately.</p>
    </div>
    <p class="text-muted">Email: <?= e($member['email'] ?? '') ?> (not changed here)</p>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
