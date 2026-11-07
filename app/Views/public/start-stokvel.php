<?php $u = $user ?? null; ?>
<div class="auth-shell auth-split">
    <section class="auth-hero">
        <p class="brand-kicker"><span class="brand-mark" aria-hidden="true">S</span><span>Create a stokvel</span></p>
        <h1>Request a new stokvel</h1>
        <p>Anyone can submit a request. Platform admins review it before the group becomes active. No access is granted until approval and claim.</p>
    </section>
    <div class="auth-card card">
        <h1>Stokvel details</h1>
        <form method="post" action="<?= e(url('/start-stokvel')) ?>" class="form-stack" novalidate>
            <?= csrf_field() ?>
            <div class="form-grid two-col">
                <div class="form-group">
                    <label class="form-label required" for="first_name">Your first name</label>
                    <input class="form-control" type="text" id="first_name" name="first_name" required value="<?= e((string) old('first_name', $u['first_name'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label required" for="last_name">Your last name</label>
                    <input class="form-control" type="text" id="last_name" name="last_name" required value="<?= e((string) old('last_name', $u['last_name'] ?? '')) ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label required" for="email">Contact email</label>
                <input class="form-control" type="email" id="email" name="email" required value="<?= e((string) old('email', $u['email'] ?? '')) ?>" <?= $u ? 'readonly' : '' ?>>
                <p class="help-text">We use this email for approval updates and the claim link. We will not reveal whether it already has an account.</p>
            </div>
            <div class="form-group">
                <label class="form-label required" for="stokvel_name">Proposed stokvel name</label>
                <input class="form-control" type="text" id="stokvel_name" name="stokvel_name" required value="<?= e((string) old('stokvel_name')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label" for="stokvel_type">Stokvel type</label>
                <select class="form-control form-select" id="stokvel_type" name="stokvel_type">
                    <?php foreach (($stokvelTypeOptions ?? []) as $opt): ?>
                        <option value="<?= e((string) ($opt['code'] ?? $opt['value'] ?? '')) ?>"<?= (string) old('stokvel_type', 'general_savings') === (string) ($opt['code'] ?? $opt['value'] ?? '') ? ' selected' : '' ?>>
                            <?= e((string) ($opt['name'] ?? $opt['label'] ?? $opt['code'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="description">Brief description (optional)</label>
                <textarea class="form-control form-textarea" id="description" name="description" rows="4"><?= e((string) old('description')) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Submit for approval</button>
        </form>
        <div class="auth-links">
            <a href="<?= e(url('/register')) ?>">Create an account first</a>
            · <a href="<?= e(url('/login')) ?>">Sign in</a>
        </div>
    </div>
</div>
