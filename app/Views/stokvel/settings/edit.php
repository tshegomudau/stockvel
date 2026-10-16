<?= view('partials/page-header', [
    'title' => 'Stokvel settings',
    'description' => 'Organisation profile, branding, contribution rules, and feature toggles.',
], null) ?>
<form method="post" action="<?= e(url('/app/settings')) ?>" class="form-stack">
<?= csrf_field() ?>
<?= method_field('PUT') ?>
<div class="form-section">
<h2>Organisation</h2>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="name">Name</label><input id="name" name="name" value="<?= e($settings['name'] ?? $stokvel['name'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="primary_colour">Brand colour</label><input type="color" id="primary_colour" name="primary_colour" value="<?= e($settings['primary_colour'] ?? $stokvel['primary_colour'] ?? '#14532d') ?>"></div>
</div>
</div>
<div class="form-section">
<h2>Finance defaults</h2>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="currency">Currency</label><input id="currency" name="currency" value="<?= e($settings['currency'] ?? 'ZAR') ?>"></div>
<div class="form-group"><label class="form-label" for="timezone">Timezone</label><input id="timezone" name="timezone" value="<?= e($settings['timezone'] ?? 'Africa/Johannesburg') ?>"></div>
</div>
</div>
<div class="form-actions"><button class="btn btn-primary" type="submit">Save settings</button></div>
</form>
