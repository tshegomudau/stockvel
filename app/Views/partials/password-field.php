<?php
/** @var array{min_length?:int,require_upper?:bool,require_lower?:bool,require_number?:bool,require_special?:bool,labels?:list<string>}|null $passwordPolicy */
$policy = $passwordPolicy ?? [];
try {
    if ($policy === []) {
        $policy = app()->container()->get(\App\Services\Auth\PasswordPolicyService::class)->clientConfig();
    }
} catch (\Throwable) {
    $policy = [
        'min_length' => 8,
        'require_upper' => true,
        'require_lower' => true,
        'require_number' => true,
        'require_special' => false,
        'labels' => ['At least 8 characters', 'At least one uppercase letter (A–Z)', 'At least one lowercase letter (a–z)', 'At least one number (0–9)'],
    ];
}
$id = $id ?? 'password';
$name = $name ?? 'password';
$label = $label ?? 'Password';
$required = isset($required) ? (bool) $required : true;
$autocomplete = $autocomplete ?? 'new-password';
$minLength = (int) ($policy['min_length'] ?? 8);
$labels = $policy['labels'] ?? [];
$showStrength = $showStrength ?? true;
$showRequirements = $showRequirements ?? true;
$configJson = e(json_encode([
    'min_length' => $minLength,
    'require_upper' => !empty($policy['require_upper']),
    'require_lower' => !empty($policy['require_lower']),
    'require_number' => !empty($policy['require_number']),
    'require_special' => !empty($policy['require_special']),
], JSON_THROW_ON_ERROR));
?>
<div class="form-group password-policy-field" data-password-policy="<?= $configJson ?>">
    <label class="form-label<?= $required ? ' required' : '' ?>" for="<?= e($id) ?>"><?= e($label) ?></label>
    <div class="password-field">
        <input
            class="form-control"
            type="password"
            id="<?= e($id) ?>"
            name="<?= e($name) ?>"
            <?= $required ? 'required' : '' ?>
            minlength="<?= $minLength ?>"
            autocomplete="<?= e($autocomplete) ?>"
            data-password-input
            aria-describedby="<?= e($id) ?>-hints"
        >
        <button type="button" class="password-toggle" data-password-toggle="<?= e($id) ?>" aria-label="Show password">Show</button>
    </div>
    <?php if ($showStrength): ?>
        <div class="password-strength" data-password-strength hidden>
            <div class="password-strength-bar" aria-hidden="true"><span data-password-strength-fill></span></div>
            <p class="password-strength-label" data-password-strength-label>Strength</p>
        </div>
    <?php endif; ?>
    <?php if ($showRequirements && $labels !== []): ?>
        <ul class="password-requirements help-text" id="<?= e($id) ?>-hints">
            <?php foreach ($labels as $reqLabel): ?>
                <li data-password-req><?= e((string) $reqLabel) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
