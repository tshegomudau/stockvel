<div class="card">
    <h1>Stokvel setup</h1>
    <p>Step <?= (int) $step ?> of <?= count($steps) ?>: <?= e(ucfirst(str_replace('_', ' ', $steps[$step] ?? ''))) ?></p>
    <div class="progress-bar" role="progressbar" aria-valuenow="<?= (int) $step ?>" aria-valuemin="1" aria-valuemax="<?= count($steps) ?>">
        <span style="width:<?= e((string) round(($step / max(1, count($steps))) * 100)) ?>%"></span>
    </div>

    <?php
    $stepKey = $steps[$step] ?? 'organisation';
    $saved = $progress[$step] ?? [];
    ?>
    <form method="post" action="<?= e(url('/app/onboarding/step/' . $step)) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?php if ($stepKey === 'organisation'): ?>
            <div class="form-group">
                <label for="name">Stokvel name</label>
                <input type="text" id="name" name="name" required value="<?= e((string) ($saved['name'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="registration_number">Registration number (optional)</label>
                <input type="text" id="registration_number" name="registration_number" value="<?= e((string) ($saved['registration_number'] ?? '')) ?>">
            </div>
        <?php elseif ($stepKey === 'contact'): ?>
            <div class="form-group"><label for="contact_email">Contact email</label><input type="email" id="contact_email" name="contact_email" value="<?= e((string) ($saved['contact_email'] ?? '')) ?>"></div>
            <div class="form-group"><label for="contact_phone">Contact phone</label><input type="tel" id="contact_phone" name="contact_phone" value="<?= e((string) ($saved['contact_phone'] ?? '')) ?>"></div>
        <?php elseif ($stepKey === 'banking'): ?>
            <div class="form-group"><label for="bank_name">Bank</label><input type="text" id="bank_name" name="bank_name" value="<?= e((string) ($saved['bank_name'] ?? '')) ?>"></div>
            <div class="form-group"><label for="account_number">Account number</label><input type="text" id="account_number" name="account_number" value="<?= e((string) ($saved['account_number'] ?? '')) ?>"></div>
        <?php elseif ($stepKey === 'contributions'): ?>
            <div class="form-group"><label for="contribution_amount">Default contribution (ZAR)</label><input type="number" step="0.01" id="contribution_amount" name="contribution_amount" value="<?= e((string) ($saved['contribution_amount'] ?? '')) ?>"></div>
            <div class="form-group"><label for="contribution_day">Collection day of month</label><input type="number" min="1" max="28" id="contribution_day" name="contribution_day" value="<?= e((string) ($saved['contribution_day'] ?? '1')) ?>"></div>
        <?php elseif ($stepKey === 'invite'): ?>
            <div class="form-group"><label for="invite_email">Invite email</label><input type="email" id="invite_email" name="invite_email" value="<?= e((string) ($saved['invite_email'] ?? '')) ?>"></div>
        <?php elseif ($stepKey === 'review'): ?>
            <p>Review your settings and activate your stokvel when ready.</p>
        <?php else: ?>
            <p>Configure <?= e($stepKey) ?> settings for your stokvel.</p>
            <div class="form-group"><label for="notes">Notes</label><textarea id="notes" name="notes" rows="3"><?= e((string) ($saved['notes'] ?? '')) ?></textarea></div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Save &amp; continue</button>
    </form>

    <?php if ($step >= count($steps)): ?>
        <form method="post" action="<?= e(url('/app/onboarding/complete')) ?>" style="margin-top:1rem;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary">Activate stokvel</button>
        </form>
    <?php endif; ?>
</div>
