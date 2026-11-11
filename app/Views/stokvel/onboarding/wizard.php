<?php
$stepKeys = $steps ?? \App\Services\Onboarding\OnboardingService::STEPS;
$current = (int) ($step ?? 1);
$total = max(1, count($stepKeys));
$percent = (int) ($percent ?? 0);
$stepsCompleted = $stepsCompleted ?? [];
$labels = [
    'organisation' => 'Stokvel details',
    'type' => 'Stokvel type',
    'branding' => 'Branding',
    'contact' => 'Contact',
    'banking' => 'Banking',
    'contributions' => 'Contributions',
    'fees' => 'Fees & penalties',
    'rules' => 'Rules & approvals',
    'invite' => 'Invite members',
    'review' => 'Review & finish',
];
$stokvelRow = $stokvel ?? [];
?>
<?= view('partials/page-header', [
    'title' => 'Stokvel setup wizard',
    'description' => 'Finish these steps to configure your stokvel. Progress is saved as you go — the wizard hides when setup is complete.',
], null) ?>

<div class="alert alert-info" role="status">
    Setup progress: <strong><?= (int) $percent ?>%</strong>
    · Step <?= $current ?> of <?= $total ?>
</div>

<div class="wizard-steps" aria-label="Onboarding progress">
    <?php
    foreach ($stepKeys as $num => $key):
        $i = (int) $num;
        $state = in_array($i, $stepsCompleted, true) || $i < $current
            ? 'is-done'
            : ($i === $current ? 'is-current' : '');
        $label = $labels[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
        ?>
        <div class="wizard-step <?= e($state) ?>">
            <strong>Step <?= $i ?></strong>
            <?= e($label) ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <?php
    $stepKey = is_string($stepKeys[$current] ?? null) ? (string) $stepKeys[$current] : 'organisation';
    $saved = [];
    if (isset($progress['step_' . $current]) && is_array($progress['step_' . $current])) {
        $saved = $progress['step_' . $current];
    } elseif (isset($progress[$stepKey]) && is_array($progress[$stepKey])) {
        $saved = $progress[$stepKey];
    }
    // Prefill organisation from live stokvel when first visiting.
    if ($stepKey === 'organisation') {
        $saved['name'] = (string) ($saved['name'] ?? $stokvelRow['name'] ?? '');
        $saved['description'] = (string) ($saved['description'] ?? $stokvelRow['description'] ?? '');
        $saved['registration_number'] = (string) ($saved['registration_number'] ?? $stokvelRow['registration_number'] ?? '');
    }
    if ($stepKey === 'type') {
        $saved['stokvel_type'] = (string) ($saved['stokvel_type'] ?? $stokvelRow['stokvel_type'] ?? 'general_savings');
    }
    ?>
    <h2 class="card-title">Step <?= $current ?> of <?= $total ?>: <?= e($labels[$stepKey] ?? 'Details') ?></h2>
    <form method="post" action="<?= e(url('/app/onboarding/' . $current)) ?>" class="form-stack mt-4">
        <?= csrf_field() ?>
        <?php if ($stepKey === 'organisation'): ?>
            <div class="form-grid two-col">
                <div class="form-group">
                    <label class="form-label required" for="name">Official name</label>
                    <input class="form-control" type="text" id="name" name="name" required value="<?= e((string) ($saved['name'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="short_name">Short name</label>
                    <input class="form-control" type="text" id="short_name" name="short_name" value="<?= e((string) ($saved['short_name'] ?? '')) ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control form-textarea" id="description" name="description" rows="3"><?= e((string) ($saved['description'] ?? '')) ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="registration_number">Registration number</label>
                <input class="form-control" type="text" id="registration_number" name="registration_number" value="<?= e((string) ($saved['registration_number'] ?? '')) ?>">
            </div>
        <?php elseif ($stepKey === 'type'): ?>
            <div class="form-group">
                <label class="form-label required" for="stokvel_type">Stokvel type</label>
                <select class="form-control form-select" id="stokvel_type" name="stokvel_type" required>
                    <?php
                    $typeOptions = $stokvelTypeOptions ?? \App\Support\StokvelTypeCatalog::activeOptions();
                    $currentType = (string) ($saved['stokvel_type'] ?? '');
                    foreach ($typeOptions as $opt):
                        $code = (string) ($opt['code'] ?? '');
                    ?>
                        <option value="<?= e($code) ?>"<?= $currentType === $code ? ' selected' : '' ?>><?= e($opt['name'] ?? $code) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="form-help">Types are defined by the platform; choose which applies to this stokvel.</p>
            </div>
        <?php elseif ($stepKey === 'branding'): ?>
            <div class="form-group">
                <label class="form-label" for="primary_colour">Primary colour</label>
                <input class="form-control" type="color" id="primary_colour" name="primary_colour" value="<?= e((string) ($saved['primary_colour'] ?? '#14532d')) ?>">
                <p class="form-help">Used for navigation and accents.</p>
            </div>
        <?php elseif ($stepKey === 'contact'): ?>
            <div class="form-grid two-col">
                <div class="form-group"><label class="form-label" for="contact_email">Contact email</label><input class="form-control" type="email" id="contact_email" name="contact_email" value="<?= e((string) ($saved['contact_email'] ?? '')) ?>"></div>
                <div class="form-group"><label class="form-label" for="contact_phone">Contact phone</label><input class="form-control" type="tel" id="contact_phone" name="contact_phone" value="<?= e((string) ($saved['contact_phone'] ?? '')) ?>"></div>
            </div>
            <div class="form-group"><label class="form-label" for="province">Province</label><input class="form-control" type="text" id="province" name="province" value="<?= e((string) ($saved['province'] ?? '')) ?>"></div>
        <?php elseif ($stepKey === 'banking'): ?>
            <div class="form-grid two-col">
                <div class="form-group"><label class="form-label" for="bank_name">Bank</label><input class="form-control" type="text" id="bank_name" name="bank_name" value="<?= e((string) ($saved['bank_name'] ?? '')) ?>"></div>
                <div class="form-group"><label class="form-label" for="account_number">Account number</label><input class="form-control" type="text" id="account_number" name="account_number" autocomplete="off" value="<?= e((string) ($saved['account_number'] ?? '')) ?>"></div>
            </div>
            <p class="form-help">Account numbers are masked in logs and most UI surfaces.</p>
        <?php elseif ($stepKey === 'contributions'): ?>
            <div class="form-grid two-col">
                <div class="form-group">
                    <label class="form-label" for="contribution_amount">Default contribution</label>
                    <div class="input-prefix"><span>R</span><input class="form-control" type="number" step="0.01" min="0" id="contribution_amount" name="contribution_amount" value="<?= e((string) ($saved['contribution_amount'] ?? '')) ?>"></div>
                </div>
                <div class="form-group"><label class="form-label" for="contribution_day">Collection day</label><input class="form-control" type="number" min="1" max="28" id="contribution_day" name="contribution_day" value="<?= e((string) ($saved['contribution_day'] ?? '1')) ?>"></div>
            </div>
        <?php elseif ($stepKey === 'fees'): ?>
            <div class="form-grid two-col">
                <div class="form-group"><label class="form-label" for="joining_fee">Joining fee</label><div class="input-prefix"><span>R</span><input class="form-control" type="number" step="0.01" id="joining_fee" name="joining_fee" value="<?= e((string) ($saved['joining_fee'] ?? '0')) ?>"></div></div>
                <div class="form-group"><label class="form-label" for="late_penalty">Late-payment penalty</label><div class="input-prefix"><span>R</span><input class="form-control" type="number" step="0.01" id="late_penalty" name="late_penalty" value="<?= e((string) ($saved['late_penalty'] ?? '0')) ?>"></div></div>
            </div>
        <?php elseif ($stepKey === 'invite'): ?>
            <div class="form-group"><label class="form-label" for="invite_email">Invite email</label><input class="form-control" type="email" id="invite_email" name="invite_email" value="<?= e((string) ($saved['invite_email'] ?? '')) ?>"></div>
            <div class="form-group"><label class="form-label" for="invite_role">Role</label>
                <select class="form-control form-select" id="invite_role" name="invite_role">
                    <?php foreach (['ordinary_member','committee_member','secretary','treasurer'] as $role): ?>
                        <option value="<?= e($role) ?>"<?= (string) ($saved['invite_role'] ?? '') === $role ? ' selected' : '' ?>><?= e(ucfirst(str_replace('_', ' ', $role))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="form-help">You can send formal invitations from Members after setup. This step records your plan.</p>
        <?php elseif ($stepKey === 'rules'): ?>
            <div class="form-group">
                <label class="form-label" for="notes">Governance notes</label>
                <textarea class="form-control form-textarea" id="notes" name="notes" rows="4" placeholder="Approval thresholds, meeting cadence, etc."><?= e((string) ($saved['notes'] ?? '')) ?></textarea>
            </div>
            <p class="form-help">Detailed rules and approval policies can be refined under Rules and Approvals after setup.</p>
        <?php elseif ($stepKey === 'review'): ?>
            <div class="alert alert-info">Review your configuration. Finishing the wizard marks setup complete and removes it from navigation.</div>
            <p class="text-muted">You can still update settings anytime from the Settings screen.</p>
        <?php else: ?>
            <div class="form-group"><label class="form-label" for="notes">Notes</label><textarea class="form-control form-textarea" id="notes" name="notes" rows="3"><?= e((string) ($saved['notes'] ?? '')) ?></textarea></div>
        <?php endif; ?>

        <div class="form-actions">
            <?php if ($current > 1): ?>
                <a class="btn btn-secondary" href="<?= e(url('/app/onboarding?step=' . ($current - 1))) ?>">Previous</a>
            <?php endif; ?>
            <?php if ($stepKey !== 'review'): ?>
                <button type="submit" class="btn btn-primary">Save &amp; continue</button>
            <?php else: ?>
                <button type="submit" class="btn btn-secondary">Save review notes</button>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($stepKey === 'review' || $current >= $total): ?>
        <form method="post" action="<?= e(url('/app/onboarding/complete')) ?>" class="mt-4">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary" data-confirm="Mark setup complete and open the dashboard?">Finish setup</button>
        </form>
    <?php endif; ?>
</div>
