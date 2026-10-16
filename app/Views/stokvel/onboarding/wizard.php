<?php
$stepKeys = $steps ?? [];
$current = (int) ($step ?? 1);
$total = max(1, count($stepKeys));
$labels = [
    'organisation' => 'Stokvel details',
    'type' => 'Stokvel type',
    'branding' => 'Branding',
    'contact' => 'Contact',
    'banking' => 'Banking',
    'contributions' => 'Contributions',
    'fees' => 'Fees & penalties',
    'committee' => 'Committee roles',
    'rules' => 'Rules & approvals',
    'invite' => 'Invite members',
    'review' => 'Review',
    'activate' => 'Activate',
];
?>
<?= view('partials/page-header', [
    'title' => 'Stokvel setup wizard',
    'description' => 'Complete each step to activate your organisation. Progress is saved as you go.',
], null) ?>

<div class="wizard-steps" aria-label="Onboarding progress">
    <?php foreach ($stepKeys as $num => $key): ?>
        <?php
        $n = (int) $num;
        if ($n < 1) { $n = array_search($key, array_values($stepKeys), true); $n = $n === false ? 0 : $n + 1; }
        // steps may be 1-indexed associative
        ?>
    <?php endforeach; ?>
    <?php
    $i = 0;
    foreach ($stepKeys as $key):
        $i++;
        $state = $i < $current ? 'is-done' : ($i === $current ? 'is-current' : '');
        $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
        ?>
        <div class="wizard-step <?= e($state) ?>">
            <strong>Step <?= $i ?></strong>
            <?= e($label) ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2 class="card-title">Step <?= $current ?> of <?= $total ?>: <?= e($labels[$stepKeys[$current] ?? ($stepKeys[$current - 1] ?? '')] ?? 'Details') ?></h2>
    <?php
    $stepKey = $stepKeys[$current] ?? ($stepKeys[$current - 1] ?? 'organisation');
    if (!is_string($stepKey)) {
        $stepKey = 'organisation';
    }
    $saved = $progress[$current] ?? ($progress[$stepKey] ?? []);
    ?>
    <form method="post" action="<?= e(url('/app/onboarding/step/' . $current)) ?>" class="form-stack mt-4">
        <?= csrf_field() ?>
        <?php if (in_array($stepKey, ['organisation', 'details', 'stokvel_details'], true)): ?>
            <div class="form-grid two-col">
                <div class="form-group">
                    <label class="form-label required" for="name">Official name</label>
                    <input type="text" id="name" name="name" required value="<?= e((string) ($saved['name'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="short_name">Short name</label>
                    <input type="text" id="short_name" name="short_name" value="<?= e((string) ($saved['short_name'] ?? '')) ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?= e((string) ($saved['description'] ?? '')) ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="registration_number">Registration number</label>
                <input type="text" id="registration_number" name="registration_number" value="<?= e((string) ($saved['registration_number'] ?? '')) ?>">
            </div>
        <?php elseif ($stepKey === 'type'): ?>
            <div class="form-group">
                <label class="form-label required" for="stokvel_type">Stokvel type</label>
                <select id="stokvel_type" name="stokvel_type" required>
                    <?php foreach (['savings','burial','grocery','investment','loan','rotational','social','custom'] as $type): ?>
                        <option value="<?= e($type) ?>"<?= (($saved['stokvel_type'] ?? '') === $type) ? ' selected' : '' ?>><?= e(ucfirst($type)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($stepKey === 'branding'): ?>
            <div class="form-group">
                <label class="form-label" for="primary_colour">Primary colour</label>
                <input type="color" id="primary_colour" name="primary_colour" value="<?= e((string) ($saved['primary_colour'] ?? '#14532d')) ?>">
                <p class="help-text">Used for navigation and accents. Ensure sufficient contrast for accessibility.</p>
            </div>
        <?php elseif ($stepKey === 'contact'): ?>
            <div class="form-grid two-col">
                <div class="form-group"><label class="form-label" for="contact_email">Contact email</label><input type="email" id="contact_email" name="contact_email" value="<?= e((string) ($saved['contact_email'] ?? '')) ?>"></div>
                <div class="form-group"><label class="form-label" for="contact_phone">Contact phone</label><input type="tel" id="contact_phone" name="contact_phone" value="<?= e((string) ($saved['contact_phone'] ?? '')) ?>"></div>
            </div>
            <div class="form-group"><label class="form-label" for="province">Province</label><input type="text" id="province" name="province" value="<?= e((string) ($saved['province'] ?? '')) ?>"></div>
        <?php elseif ($stepKey === 'banking'): ?>
            <div class="form-grid two-col">
                <div class="form-group"><label class="form-label" for="bank_name">Bank</label><input type="text" id="bank_name" name="bank_name" value="<?= e((string) ($saved['bank_name'] ?? '')) ?>"></div>
                <div class="form-group"><label class="form-label" for="account_number">Account number</label><input type="text" id="account_number" name="account_number" autocomplete="off" value="<?= e((string) ($saved['account_number'] ?? '')) ?>"></div>
            </div>
            <p class="help-text">Account numbers are masked in logs and most UI surfaces.</p>
        <?php elseif ($stepKey === 'contributions'): ?>
            <div class="form-grid two-col">
                <div class="form-group">
                    <label class="form-label" for="contribution_amount">Default contribution</label>
                    <div class="input-prefix"><span>R</span><input type="number" step="0.01" min="0" id="contribution_amount" name="contribution_amount" value="<?= e((string) ($saved['contribution_amount'] ?? '')) ?>"></div>
                </div>
                <div class="form-group"><label class="form-label" for="contribution_day">Collection day</label><input type="number" min="1" max="28" id="contribution_day" name="contribution_day" value="<?= e((string) ($saved['contribution_day'] ?? '1')) ?>"></div>
            </div>
        <?php elseif (in_array($stepKey, ['fees', 'penalties'], true)): ?>
            <div class="form-grid two-col">
                <div class="form-group"><label class="form-label" for="joining_fee">Joining fee</label><div class="input-prefix"><span>R</span><input type="number" step="0.01" id="joining_fee" name="joining_fee" value="<?= e((string) ($saved['joining_fee'] ?? '0')) ?>"></div></div>
                <div class="form-group"><label class="form-label" for="late_penalty">Late-payment penalty</label><div class="input-prefix"><span>R</span><input type="number" step="0.01" id="late_penalty" name="late_penalty" value="<?= e((string) ($saved['late_penalty'] ?? '0')) ?>"></div></div>
            </div>
        <?php elseif ($stepKey === 'invite'): ?>
            <div class="form-group"><label class="form-label" for="invite_email">Invite email</label><input type="email" id="invite_email" name="invite_email" value="<?= e((string) ($saved['invite_email'] ?? '')) ?>"></div>
            <div class="form-group"><label class="form-label" for="invite_role">Role</label>
                <select id="invite_role" name="invite_role">
                    <?php foreach (['ordinary_member','committee_member','secretary','treasurer','chairperson'] as $role): ?>
                        <option value="<?= e($role) ?>"><?= e(ucfirst(str_replace('_', ' ', $role))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php elseif ($stepKey === 'review'): ?>
            <div class="alert alert-info">Review your configuration carefully. Activation makes the stokvel available to invited members.</div>
            <p class="text-muted">You can still update settings after activation from the Settings screen.</p>
        <?php else: ?>
            <p class="text-muted">Configure <?= e(str_replace('_', ' ', $stepKey)) ?> for your stokvel.</p>
            <div class="form-group"><label class="form-label" for="notes">Notes</label><textarea id="notes" name="notes" rows="3"><?= e((string) ($saved['notes'] ?? '')) ?></textarea></div>
        <?php endif; ?>

        <div class="form-actions">
            <?php if ($current > 1): ?>
                <a class="btn btn-secondary" href="<?= e(url('/app/onboarding?step=' . ($current - 1))) ?>">Previous</a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><?= $stepKey === 'review' ? 'Save review' : 'Save & continue' ?></button>
        </div>
    </form>

    <?php if ($stepKey === 'review' || $current >= $total): ?>
        <form method="post" action="<?= e(url('/app/onboarding/complete')) ?>" class="mt-4">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" data-confirm="Activate this stokvel now?">Activate stokvel</button>
        </form>
    <?php endif; ?>
</div>
