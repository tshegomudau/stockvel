<div class="grid grid-2">
    <form method="post" action="<?= e(url('/app/settings')) ?>" class="card form-stack">
        <?= csrf_field() ?>
        <h2>Organisation</h2>
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" value="<?= e($stokvel['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
            <label for="registration_number">Registration number</label>
            <input type="text" id="registration_number" name="registration_number" value="<?= e($stokvel['registration_number'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="3"><?= e($stokvel['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="primary_colour">Brand colour</label>
            <input type="color" id="primary_colour" name="primary_colour" value="<?= e($stokvel['primary_colour'] ?? '#0d6b4f') ?>">
        </div>
        <h2>Financial defaults</h2>
        <div class="form-group">
            <label for="currency">Currency</label>
            <input type="text" id="currency" name="currency" value="<?= e($settings['currency'] ?? 'ZAR') ?>" readonly>
        </div>
        <div class="form-group">
            <label for="timezone">Timezone</label>
            <input type="text" id="timezone" name="timezone" value="<?= e($settings['timezone'] ?? 'Africa/Johannesburg') ?>">
        </div>
        <div class="form-group">
            <label for="contribution_grace_days">Contribution grace (days)</label>
            <input type="number" id="contribution_grace_days" name="contribution_grace_days" value="<?= e((string) ($settings['contribution_grace_days'] ?? '7')) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Save settings</button>
    </form>
    <div class="card">
        <h2>Banking (masked)</h2>
        <p>Account: <?= e(mask_bank($settings['account_number'] ?? '')) ?></p>
        <p class="text-muted">Full banking details are stored securely and never shown in full on screen.</p>
    </div>
</div>
