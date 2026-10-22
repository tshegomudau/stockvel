<?= view('partials/page-header', [
    'title' => 'Stokvel settings',
    'description' => 'Organisation profile, contribution rules, banking, and feature toggles. Role permissions and category catalogues live in the shortcuts below.',
], null) ?>

<section class="form-section">
    <h2>Configuration shortcuts</h2>
    <p class="text-muted mb-4">Jump to related setup screens. You need the matching permission to open each link.</p>
    <div class="form-actions">
        <?php if (can('settings.manage')): ?>
            <a class="btn btn-secondary" href="<?= e(route('app.settings.roles')) ?>">Roles &amp; permissions</a>
            <a class="btn btn-secondary" href="<?= e(route('app.settings.categories')) ?>">Category catalogues</a>
        <?php endif; ?>
        <?php if (can('loans.manage') && module_enabled('loans')): ?>
            <a class="btn btn-secondary" href="<?= e(route('app.loan_products')) ?>">Loan products</a>
        <?php endif; ?>
    </div>
</section>

<form method="post" action="<?= e(url('/app/settings')) ?>" class="form-stack">
<?= csrf_field() ?>
<?= method_field('PUT') ?>

<div class="form-section">
<h2>Organisation</h2>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="name">Name</label>
<input id="name" name="name" value="<?= e($stokvel['name'] ?? '') ?>" required></div>
<div class="form-group"><label class="form-label" for="registration_number">Registration number</label>
<input id="registration_number" name="registration_number" value="<?= e($stokvel['registration_number'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="stokvel_type">Stokvel type</label>
<select id="stokvel_type" name="stokvel_type" required>
<?php
$currentType = (string) ($stokvel['stokvel_type'] ?? 'general_savings');
$typeOptions = $stokvelTypeOptions ?? [];
$seen = false;
foreach ($typeOptions as $opt):
    $code = (string) ($opt['code'] ?? '');
    if ($code === $currentType) {
        $seen = true;
    }
?>
<option value="<?= e($code) ?>"<?= $currentType === $code ? ' selected' : '' ?>><?= e($opt['name'] ?? $code) ?></option>
<?php endforeach;
if (!$seen && $currentType !== ''): ?>
<option value="<?= e($currentType) ?>" selected><?= e($currentType) ?> (current)</option>
<?php endif; ?>
</select>
<p class="form-help">Choose from the platform catalogue. Platform admins manage which types are available.</p></div>
<div class="form-group"><label class="form-label" for="branding_primary_colour">Brand colour</label>
<input type="color" id="branding_primary_colour" name="branding_primary_colour" value="<?= e($settings['branding_primary_colour'] ?? '#14532d') ?>">
<p class="form-help">Applies to buttons, links, and sidebar accents across the app.</p></div>
</div>
<div class="form-group"><label class="form-label" for="description">Description</label>
<textarea id="description" name="description" rows="3"><?= e($stokvel['description'] ?? '') ?></textarea></div>
<?php if (($stokvel['status'] ?? '') !== 'active'): ?>
<div class="callout callout-warning">
<label class="checkbox">
<input type="checkbox" name="activate_stokvel" value="1">
Activate this stokvel (removes setup wizard; configuration stays in Settings)
</label>
<p class="form-help">Current status: <?= e($stokvel['status'] ?? 'draft') ?>. Activation is permanent for day-to-day use.</p>
</div>
<?php else: ?>
<p class="text-muted">Status: <strong>Active</strong><?= !empty($stokvel['activated_at']) ? ' since ' . e(sa_date($stokvel['activated_at'])) : '' ?>.</p>
<?php endif; ?>
</div>

<div class="form-section">
<h2>Contributions &amp; arrears</h2>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="minimum_contribution">Minimum contribution (ZAR)</label>
<input id="minimum_contribution" name="minimum_contribution" type="number" step="0.01" min="0" value="<?= e((string) ($settings['minimum_contribution'] ?? '')) ?>"></div>
<div class="form-group"><label class="form-label" for="contribution_frequency">Frequency</label>
<select id="contribution_frequency" name="contribution_frequency">
<?php foreach (['weekly','monthly','quarterly','annual'] as $f): ?>
<option value="<?= e($f) ?>"<?= ($settings['contribution_frequency'] ?? 'monthly') === $f ? ' selected' : '' ?>><?= e(ucfirst($f)) ?></option>
<?php endforeach; ?>
</select></div>
<div class="form-group"><label class="form-label" for="payment_due_day">Payment due day of month</label>
<input id="payment_due_day" name="payment_due_day" type="number" min="1" max="28" value="<?= e((string) ($settings['payment_due_day'] ?? $settings['contribution_day'] ?? '1')) ?>"></div>
<div class="form-group"><label class="form-label" for="arrears_after_days">Mark in arrears after (days past due)</label>
<input id="arrears_after_days" name="arrears_after_days" type="number" min="0" value="<?= e((string) ($settings['arrears_after_days'] ?? $settings['grace_period_days'] ?? '7')) ?>"></div>
<div class="form-group"><label class="form-label" for="grace_period_days">Grace period (days)</label>
<input id="grace_period_days" name="grace_period_days" type="number" min="0" value="<?= e((string) ($settings['grace_period_days'] ?? '7')) ?>"></div>
<div class="form-group"><label class="form-label" for="year_end_month">Year-end month</label>
<input id="year_end_month" name="year_end_month" type="number" min="1" max="12" value="<?= e((string) ($settings['year_end_month'] ?? '12')) ?>"></div>
</div>
</div>

<!--div class="form-section">
<h2>Features &amp; governance</h2>
<p class="text-muted">Loans, dual approval, notifications, and payout mode are managed by platform administrators on the stokvel record.</p>
<dl class="detail-list">
    <dt>Loans</dt><dd><?= !empty($settings['loans_enabled']) ? 'Enabled' : 'Disabled' ?></dd>
    <dt>Dual approval</dt><dd><?= !empty($settings['require_dual_approval']) ? 'Required' : 'Not required' ?></dd>
    <dt>Email notifications</dt><dd><?= ($settings['notification_email_enabled'] ?? 1) ? 'On' : 'Off' ?></dd>
    <dt>SMS notifications</dt><dd><?= !empty($settings['notification_sms_enabled']) ? 'On' : 'Off' ?></dd>
    <dt>Payout mode</dt><dd><?= e(ucfirst(str_replace('_', ' ', (string) ($settings['payout_mode'] ?? 'rotational')))) ?></dd>
</dl>
</div-->

<div class="form-section">
<h2>Banking (display / records)</h2>
<div class="form-grid two-col">
<div class="form-group"><label class="form-label" for="bank_name">Bank name</label>
<input id="bank_name" name="bank_name" value="<?= e($settings['bank_name'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="bank_account_name">Account name</label>
<input id="bank_account_name" name="bank_account_name" value="<?= e($settings['bank_account_name'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="bank_account_number_masked">Account number (masked)</label>
<input id="bank_account_number_masked" name="bank_account_number_masked" value="<?= e($settings['bank_account_number_masked'] ?? '') ?>"></div>
<div class="form-group"><label class="form-label" for="bank_branch_code">Branch code</label>
<input id="bank_branch_code" name="bank_branch_code" value="<?= e($settings['bank_branch_code'] ?? '') ?>"></div>
</div>
</div>

<div class="form-actions">
    <button class="btn btn-primary" type="submit">Save settings</button>
    <a class="btn btn-secondary" href="<?= e(route('app.settings.roles')) ?>">Manage roles &amp; permissions</a>
</div>
</form>
