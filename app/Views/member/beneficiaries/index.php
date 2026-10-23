<?= view('partials/page-header', [
    'title' => 'My beneficiaries',
    'description' => 'Add or remove your beneficiaries. Active shares must always total exactly 100%. Changes you make are applied immediately.',
], null) ?>

<p class="mb-4">
    Current active share total:
    <strong class="<?= (($shareTotal ?? 0) == 100) ? 'text-success' : 'text-danger' ?>"><?= e(number_format((float) ($shareTotal ?? 0), 2)) ?>%</strong>
</p>

<?php if (!empty($beneficiaries)): ?>
    <?php foreach ($beneficiaries as $b): ?>
        <?php $bid = (int) ($b['id'] ?? 0); ?>
        <div class="card mb-4 form-stack">
            <h2><?= e($b['full_name'] ?? '') ?><?= !empty($b['is_primary']) ? ' (primary)' : '' ?></h2>
            <form method="post" action="<?= e(url('/member/beneficiaries/' . $bid)) ?>" class="form-stack">
                <?= csrf_field() ?>
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label class="form-label">Full name</label>
                        <input name="full_name" required value="<?= e((string) ($b['full_name'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Relationship</label>
                        <input name="relationship" required value="<?= e((string) ($b['relationship'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-grid two-col">
                    <div class="form-group">
                        <label class="form-label">Share %</label>
                        <input name="share_percent" type="number" step="0.01" min="0" max="100" required value="<?= e((string) ($b['share_percent'] ?? '')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input name="phone" value="<?= e((string) ($b['phone'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input name="email" type="email" value="<?= e((string) ($b['email'] ?? '')) ?>">
                </div>
                <label class="checkbox-inline">
                    <input type="checkbox" name="is_primary" value="1"<?= !empty($b['is_primary']) ? ' checked' : '' ?>> Primary beneficiary
                </label>
                <div class="page-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
            <form method="post" action="<?= e(url('/member/beneficiaries/' . $bid . '/delete')) ?>" class="mt-2">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger" data-confirm="Remove this beneficiary?">Remove</button>
            </form>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p class="text-muted mb-4">No beneficiaries yet. Add one with 100% share, or add several that total 100%.</p>
<?php endif; ?>

<section class="card form-stack">
    <h2>Add beneficiary</h2>
    <form method="post" action="<?= e(url('/member/beneficiaries')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-grid two-col">
            <div class="form-group">
                <label class="form-label required" for="full_name">Full name</label>
                <input id="full_name" name="full_name" required>
            </div>
            <div class="form-group">
                <label class="form-label required" for="relationship">Relationship</label>
                <input id="relationship" name="relationship" required>
            </div>
        </div>
        <div class="form-grid two-col">
            <div class="form-group">
                <label class="form-label required" for="share_percent">Share %</label>
                <?php
                $remaining = max(0, round(100 - (float) ($shareTotal ?? 0), 2));
                $defaultShare = ($shareTotal ?? 0) <= 0 ? 100 : $remaining;
                ?>
                <input id="share_percent" name="share_percent" type="number" step="0.01" min="0" max="100" required value="<?= e((string) $defaultShare) ?>">
                <p class="form-help">Remaining to reach 100%: <?= e(number_format($remaining, 2)) ?>%</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="id_number">ID number</label>
                <input id="id_number" name="id_number">
            </div>
        </div>
        <div class="form-grid two-col">
            <div class="form-group">
                <label class="form-label" for="phone">Phone</label>
                <input id="phone" name="phone">
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input id="email" name="email" type="email">
            </div>
        </div>
        <label class="checkbox-inline">
            <input type="checkbox" name="is_primary" value="1"> Primary beneficiary
        </label>
        <button type="submit" class="btn btn-primary">Add beneficiary</button>
    </form>
</section>
