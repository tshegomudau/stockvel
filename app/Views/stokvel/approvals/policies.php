<?php
$approvalRequestTypes = $approvalRequestTypes ?? \App\Services\Approvals\ApprovalService::REQUEST_TYPE_OPTIONS;
$roleOptions = $roleOptions ?? [];
$policies = $policies ?? [];
$editPolicy = $editPolicy ?? null;
$isEdit = is_array($editPolicy);

$editType = (string) ($editPolicy['request_type'] ?? '');
$editMin = (string) ($editPolicy['min_approvals'] ?? old('policy_min_approvals', '2'));
$editThreshold = (string) ($editPolicy['amount_threshold'] ?? old('policy_amount_threshold', ''));
$editRolesRaw = $editPolicy['required_role_codes'] ?? old('policy_required_roles', []);
$editRoles = [];
if (is_string($editRolesRaw) && $editRolesRaw !== '') {
    $decoded = json_decode($editRolesRaw, true);
    $editRoles = is_array($decoded) ? array_map('strval', $decoded) : [];
} elseif (is_array($editRolesRaw)) {
    $editRoles = array_map('strval', $editRolesRaw);
}
$isCustomType = $editType !== '' && !array_key_exists($editType, $approvalRequestTypes);
$formAction = $isEdit
    ? url('/app/approvals/policies/' . (int) $editPolicy['id'])
    : url('/app/approvals/policies');
$roleNameByCode = [];
foreach ($roleOptions as $role) {
    $roleNameByCode[(string) ($role['code'] ?? '')] = (string) ($role['name'] ?? $role['code'] ?? '');
}
?>
<?= view('partials/page-header', [
    'title' => $isEdit ? 'Edit approval threshold' : 'Approval thresholds',
    'description' => 'Define how many independent approvals each request type needs, optional amount bands, and required roles among approvers.',
], null) ?>

<p class="text-muted mb-4">
    <a href="<?= e(url('/app/approvals')) ?>">&larr; Approvals inbox</a>
    · <a href="<?= e(url('/app/settings')) ?>">Settings</a>
</p>

<section class="form-section card mb-6">
    <h2>Default fallback</h2>
    <p class="text-muted">Used when no matching policy exists for a request.</p>
    <form method="post" action="<?= e(url('/app/approvals/policies')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <div class="form-grid two-col">
            <div class="form-group">
                <label class="form-label" for="default_min_approvals">Default minimum approvals</label>
                <input id="default_min_approvals" name="default_min_approvals" type="number" min="1" max="20"
                       value="<?= e((string) ($settings['default_min_approvals'] ?? (!empty($settings['require_dual_approval']) ? '2' : '1'))) ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-secondary">Save default</button>
    </form>
</section>

<section class="form-section card mb-6">
    <h2><?= $isEdit ? 'Update policy' : 'Add policy' ?></h2>
    <?php if ($isEdit): ?>
        <p class="text-muted">Request type stays fixed. Change min approvals, amount band, or required roles.</p>
    <?php else: ?>
        <p class="text-muted">Choose a managed request type (or custom code), optional amount threshold, and roles that must appear among approvers.</p>
    <?php endif; ?>
    <form method="post" action="<?= e($formAction) ?>" class="form-stack">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <?= method_field('PUT') ?>
        <?php else: ?>
            <input type="hidden" name="create_policy" value="1">
        <?php endif; ?>
        <div class="form-grid two-col">
            <div class="form-group">
                <label class="form-label" for="policy_request_type">Request type</label>
                <?php if ($isEdit): ?>
                    <?php
                    $typeLabel = $editType === ''
                        ? 'Default (any type)'
                        : ($approvalRequestTypes[$editType] ?? $editType);
                    ?>
                    <input type="text" value="<?= e((string) $typeLabel) ?>" disabled>
                    <input type="hidden" name="policy_request_type" value="<?= e($editType) ?>">
                <?php else: ?>
                    <select id="policy_request_type" name="policy_request_type">
                        <?php foreach ($approvalRequestTypes as $code => $label): ?>
                            <option value="<?= e((string) $code) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                        <option value="__custom__">Custom type…</option>
                    </select>
                    <input id="policy_request_type_custom" name="policy_request_type_custom" class="mt-2"
                           placeholder="e.g. special_levy" hidden aria-label="Custom request type code">
                    <p class="form-help">Blank default covers any type without a more specific rule.</p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="form-label" for="policy_min_approvals">Minimum approvals</label>
                <input id="policy_min_approvals" name="policy_min_approvals" type="number" min="1" max="20"
                       value="<?= e($isEdit ? $editMin : (string) old('policy_min_approvals', '2')) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="policy_amount_threshold">Amount threshold (ZAR)</label>
                <input id="policy_amount_threshold" name="policy_amount_threshold" type="number" step="0.01" min="0"
                       placeholder="Optional — apply when amount ≥ this"
                       value="<?= e($isEdit ? $editThreshold : (string) old('policy_amount_threshold', '')) ?>">
                <p class="form-help">Leave blank for a type-wide rule. Higher thresholds win when amount matches.</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="policy_required_roles">Required roles</label>
                <select id="policy_required_roles" name="policy_required_roles[]" multiple
                        size="<?= max(4, min(8, count($roleOptions) ?: 4)) ?>">
                    <?php foreach ($roleOptions as $role): ?>
                        <?php $code = (string) ($role['code'] ?? ''); ?>
                        <option value="<?= e($code) ?>"<?= in_array($code, $editRoles, true) ? ' selected' : '' ?>>
                            <?= e((string) ($role['name'] ?? $code)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="form-help">Hold Ctrl/Cmd to multi-select. Each selected role must appear among approvers.</p>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update policy' : 'Add policy' ?></button>
            <?php if ($isEdit): ?>
                <a class="btn btn-secondary" href="<?= e(url('/app/approvals/policies')) ?>">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php if ($policies === []): ?>
    <?= view('partials/empty', [
        'emptyTitle' => 'No policies yet',
        'emptyMessage' => 'Add type-specific or amount-based rules above. Until then, the default minimum applies.',
    ], null) ?>
<?php else: ?>
<div class="table-wrap">
<table class="table">
<thead>
<tr>
    <th>Type</th>
    <th>Min approvals</th>
    <th>Amount ≥</th>
    <th>Required roles</th>
    <th>Status</th>
    <th></th>
</tr>
</thead>
<tbody>
<?php foreach ($policies as $pol): ?>
<?php
    $typeCode = (string) ($pol['request_type'] ?? '');
    $typeLabel = $typeCode === '' ? 'Default' : ($approvalRequestTypes[$typeCode] ?? $typeCode);
    $rolesRaw = $pol['required_role_codes'] ?? null;
    $rolesList = [];
    if (is_string($rolesRaw) && $rolesRaw !== '') {
        $decoded = json_decode($rolesRaw, true);
        $rolesList = is_array($decoded) ? $decoded : (preg_split('/\s*,\s*/', $rolesRaw) ?: []);
    } elseif (is_array($rolesRaw)) {
        $rolesList = $rolesRaw;
    }
    $roleLabels = [];
    foreach ($rolesList as $code) {
        $code = (string) $code;
        $roleLabels[] = $roleNameByCode[$code] ?? $code;
    }
    $pid = (int) ($pol['id'] ?? 0);
    $active = !empty($pol['is_active']);
?>
<tr>
    <td><?= e((string) $typeLabel) ?><?php if ($typeCode !== ''): ?> <code class="text-muted"><?= e($typeCode) ?></code><?php endif; ?></td>
    <td><?= (int) ($pol['min_approvals'] ?? 1) ?></td>
    <td><?= $pol['amount_threshold'] !== null && $pol['amount_threshold'] !== '' ? e(money($pol['amount_threshold'])) : '—' ?></td>
    <td><?= $roleLabels !== [] ? e(implode(', ', $roleLabels)) : '—' ?></td>
    <td><span class="badge"><?= $active ? 'active' : 'inactive' ?></span></td>
    <td class="actions">
        <a class="btn btn-sm btn-secondary" href="<?= e(url('/app/approvals/policies/' . $pid . '/edit')) ?>">Edit</a>
        <?php if ($active): ?>
            <form method="post" action="<?= e(url('/app/approvals/policies/' . $pid . '/deactivate')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-ghost" type="submit">Deactivate</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('/app/approvals/policies/' . $pid . '/activate')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-primary" type="submit">Activate</button>
            </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/app/approvals/policies/' . $pid)) ?>" class="inline-form"
              onsubmit="return confirm('Delete this approval policy?');">
            <?= csrf_field() ?>
            <?= method_field('DELETE') ?>
            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<?php if (!$isEdit): ?>
<script>
(function () {
    var sel = document.getElementById('policy_request_type');
    var custom = document.getElementById('policy_request_type_custom');
    if (!sel || !custom) return;
    function sync() {
        var isCustom = sel.value === '__custom__';
        custom.hidden = !isCustom;
        custom.required = isCustom;
        if (!isCustom) custom.value = '';
    }
    sel.addEventListener('change', sync);
    sync();
})();
</script>
<?php endif; ?>
