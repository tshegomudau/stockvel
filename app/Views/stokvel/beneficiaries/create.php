<?= view('partials/page-header', [
    'title' => 'Add beneficiary',
    'description' => 'Link a beneficiary to a member. Active shares for a member must total 100% or less.',
], null) ?>

<div class="card">
    <form method="post" action="<?= e(url('/app/beneficiaries')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="membership_id">Member</label>
            <select id="membership_id" name="membership_id" required>
                <option value="">Select member</option>
                <?php
                $selectedMember = (string) old('membership_id', (string) ($preselectMemberId ?? ''));
                foreach ($members ?? [] as $m):
                    $mid = (string) ($m['id'] ?? '');
                    ?>
                    <option value="<?= e($mid) ?>"<?= $selectedMember === $mid ? ' selected' : '' ?>>
                        <?= e(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''))) ?>
                        (<?= e($m['member_number'] ?? '') ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-grid two-col">
            <div class="form-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" required maxlength="200" value="<?= e((string) old('full_name', '')) ?>">
            </div>
            <div class="form-group">
                <label for="relationship">Relationship</label>
                <input type="text" id="relationship" name="relationship" required maxlength="64" value="<?= e((string) old('relationship', '')) ?>" placeholder="Spouse, child, sibling…">
            </div>
        </div>
        <div class="form-grid two-col">
            <div class="form-group">
                <label for="share_percent">Share %</label>
                <input type="number" id="share_percent" name="share_percent" required min="0" max="100" step="0.01" value="<?= e((string) old('share_percent', '100')) ?>">
            </div>
            <div class="form-group">
                <label for="id_number">ID number <span class="text-muted">(optional)</span></label>
                <input type="text" id="id_number" name="id_number" maxlength="13" inputmode="numeric" autocomplete="off" value="<?= e((string) old('id_number', '')) ?>">
                <p class="text-muted">Stored masked for privacy; never shown in full after save.</p>
            </div>
        </div>
        <div class="form-grid two-col">
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" maxlength="32" value="<?= e((string) old('phone', '')) ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="255" value="<?= e((string) old('email', '')) ?>">
            </div>
        </div>
        <label class="checkbox">
            <input type="checkbox" name="is_primary" value="1"<?= old('is_primary') ? ' checked' : '' ?>>
            Primary beneficiary
        </label>
        <div class="toolbar">
            <button type="submit" class="btn btn-primary">Save beneficiary</button>
            <a class="btn btn-secondary" href="<?= e(url('/app/beneficiaries')) ?>">Cancel</a>
        </div>
    </form>
</div>
