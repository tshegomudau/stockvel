<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Exceptions\HttpException;
use App\Services\Platform\PlatformSettingsService;

/**
 * Platform-configurable password requirements and strength scoring.
 */
final class PasswordPolicyService
{
    public function __construct(private readonly PlatformSettingsService $settings)
    {
    }

    /** @return array{min_length:int,require_upper:bool,require_lower:bool,require_number:bool,require_special:bool} */
    public function rules(): array
    {
        $all = $this->settings->all();
        $min = (int) ($all['password_min_length'] ?? 8);
        if ($min < 6) {
            $min = 6;
        }
        if ($min > 128) {
            $min = 128;
        }

        return [
            'min_length' => $min,
            'require_upper' => (bool) ($all['password_require_upper'] ?? true),
            'require_lower' => (bool) ($all['password_require_lower'] ?? true),
            'require_number' => (bool) ($all['password_require_number'] ?? true),
            'require_special' => (bool) ($all['password_require_special'] ?? false),
        ];
    }

    /** @return list<string> */
    public function requirementLabels(): array
    {
        $rules = $this->rules();
        $labels = ['At least ' . $rules['min_length'] . ' characters'];
        if ($rules['require_upper']) {
            $labels[] = 'At least one uppercase letter (A–Z)';
        }
        if ($rules['require_lower']) {
            $labels[] = 'At least one lowercase letter (a–z)';
        }
        if ($rules['require_number']) {
            $labels[] = 'At least one number (0–9)';
        }
        if ($rules['require_special']) {
            $labels[] = 'At least one special character (!@#$%^&* etc.)';
        }
        return $labels;
    }

    /** @return list<string> */
    public function validate(string $password): array
    {
        $rules = $this->rules();
        $errors = [];
        if (strlen($password) < $rules['min_length']) {
            $errors[] = 'Password must be at least ' . $rules['min_length'] . ' characters.';
        }
        if ($rules['require_upper'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must include an uppercase letter.';
        }
        if ($rules['require_lower'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must include a lowercase letter.';
        }
        if ($rules['require_number'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must include a number.';
        }
        if ($rules['require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must include a special character.';
        }
        return $errors;
    }

    public function assertValid(string $password): void
    {
        $errors = $this->validate($password);
        if ($errors !== []) {
            throw new HttpException(422, $errors[0]);
        }
    }

    /** Strength score 0–4 for UI meters. */
    public function strengthScore(string $password): int
    {
        if ($password === '') {
            return 0;
        }
        $score = 0;
        $len = strlen($password);
        $rules = $this->rules();
        if ($len >= $rules['min_length']) {
            $score++;
        }
        if ($len >= max(12, $rules['min_length'] + 4)) {
            $score++;
        }
        $classes = 0;
        if (preg_match('/[a-z]/', $password)) {
            $classes++;
        }
        if (preg_match('/[A-Z]/', $password)) {
            $classes++;
        }
        if (preg_match('/[0-9]/', $password)) {
            $classes++;
        }
        if (preg_match('/[^A-Za-z0-9]/', $password)) {
            $classes++;
        }
        if ($classes >= 2) {
            $score++;
        }
        if ($classes >= 3) {
            $score++;
        }
        return min(4, $score);
    }

    /** @return array<string, mixed> */
    public function clientConfig(): array
    {
        $rules = $this->rules();
        return [
            'min_length' => $rules['min_length'],
            'require_upper' => $rules['require_upper'],
            'require_lower' => $rules['require_lower'],
            'require_number' => $rules['require_number'],
            'require_special' => $rules['require_special'],
            'labels' => $this->requirementLabels(),
        ];
    }
}
