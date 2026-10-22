<?php
$item = $item ?? [];
$itemId = (int) ($item['id'] ?? 0);
$status = (string) old('status', (string) ($item['status'] ?? 'active'));
?>
<?= view('partials/page-header', [
    'title' => 'Edit beneficiary',
    'description' => 'Update details for ' . (string) ($item['full_name'] ?? 'this beneficiary') . '.',
], null) ?>

<div class="card">
    <form method="post" action="<?= e(url('/app/beneficiaries/' . $itemId)) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="membership_id">Member</label>
            <select id="membership_id" name="membership_id" required>
                <?php
                $selectedMember = (string) old('membership_id', (string) ($item['membership_id'] ?? ''));
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
                <input type="text" id="full_name" name="full_name" required maxlength="200" value="<?= e((string) old('full_name', $item['full_name'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="relationship">Relationship</label>
                <input type="text" id="relationship" name="relationship" required maxlength="64" value="<?= e((string) old('relationship', $item['relationship'] ?? '')) ?>">
            </div>
        </div>
        <div class="form-grid two-col">
            <div class="form-group">
                <label for="share_percent">Share %</label>
                <input type="number" id="share_percent" name="share_percent" required min="0" max="100" step="0.01" value="<?= e((string) old('share_percent', (string) ($item['share_percent'] ?? '100'))) ?>">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option>
                    <option value="inactive"<?= $status === 'inactive' ? ' selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="id_number">ID number <span class="text-muted">(optional)</span></label>
            <input type="text" id="id_number" name="id_number" maxlength="13" inputmode="numeric" autocomplete="off" value="" placeholder="<?= e((string) ($item['id_number'] ?? '') !== '' ? 'Leave blank to keep current (masked)' : 'Optional') ?>">
            <?php if (!empty($item['id_number'])): ?>
                <p class="text-muted">On file: <?= e((string) $item['id_number']) ?></p>
            <?php endif; ?>
        </div>
        <div class="form-grid two-col">
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="tel" id="phone" name="phone" maxlength="32" value="<?= e((string) old('phone', $item['phone'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" maxlength="255" value="<?= e((string) old('email', $item['email'] ?? '')) ?>">
            </div>
        </div>
        <label class="checkbox">
            <input type="checkbox" name="is_primary" value="1"<?= old('is_primary', !empty($item['is_primary']) ? '1' : '') ? ' checked' : '' ?>>
            Primary beneficiary
        </label>
        <div class="toolbar">
            <button type="submit" class="btn btn-primary">Update beneficiary</button>
            <a class="btn btn-secondary" href="<?= e(url('/app/beneficiaries/' . $itemId)) ?>">Cancel</a>
        </div>
    </form>
</div>
