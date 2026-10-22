<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];

    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = []
    ) {
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    public function fails(): bool
    {
        $this->errors = [];
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            foreach ($rules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }
                $this->apply($field, $value, $rule, $params);
            }
        }
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        if ($this->fails()) {
            throw new \InvalidArgumentException('Validation failed.');
        }
        $out = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $out[$field] = $this->data[$field];
            }
        }
        return $out;
    }

    private function apply(string $field, mixed $value, string $rule, array $params): void
    {
        $failed = false;
        switch ($rule) {
            case 'required':
                $failed = $value === null || $value === '' || $value === [];
                break;
            case 'string':
                $failed = $value !== null && $value !== '' && !is_string($value) && !is_numeric($value);
                break;
            case 'email':
                $failed = $value !== null && $value !== '' && !filter_var((string) $value, FILTER_VALIDATE_EMAIL);
                break;
            case 'min':
                $min = (int) ($params[0] ?? 0);
                if (is_numeric($value) && !is_string($value)) {
                    $failed = (float) $value < $min;
                } else {
                    $failed = is_string($value) && mb_strlen($value) < $min;
                }
                break;
            case 'max':
                $max = (int) ($params[0] ?? 0);
                if (is_numeric($value) && !is_string($value)) {
                    $failed = (float) $value > $max;
                } else {
                    $failed = is_string($value) && mb_strlen($value) > $max;
                }
                break;
            case 'numeric':
                $failed = $value !== null && $value !== '' && !is_numeric($value);
                break;
            case 'integer':
                $failed = $value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false;
                break;
            case 'in':
                $failed = $value !== null && $value !== '' && !in_array((string) $value, $params, true);
                break;
            case 'confirmed':
                $failed = ($this->data[$field] ?? null) !== ($this->data[$field . '_confirmation'] ?? null);
                break;
            case 'date':
                $failed = $value !== null && $value !== '' && strtotime((string) $value) === false;
                break;
            case 'boolean':
                $failed = $value !== null && !in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true);
                break;
            case 'regex':
                $failed = $value !== null && $value !== '' && !preg_match($params[0] ?? '/.*/', (string) $value);
                break;
            case 'nullable':
                return;
        }

        if ($failed) {
            $key = "{$field}.{$rule}";
            $label = ucfirst(str_replace('_', ' ', $field));
            $default = match ($rule) {
                'required' => "Please enter the {$label}.",
                'email' => "Please enter a valid email address.",
                'min' => "{$label} must be at least " . ($params[0] ?? 0) . " characters.",
                'max' => "{$label} must be at most " . ($params[0] ?? 0) . " characters.",
                'numeric' => "{$label} must be a number.",
                'integer' => "{$label} must be a whole number.",
                'in' => "Please choose a valid {$label}.",
                'confirmed' => "{$label} confirmation does not match.",
                'date' => "Please enter a valid date for {$label}.",
                default => "{$label} is invalid.",
            };
            $this->errors[$field][] = $this->messages[$key] ?? $this->messages[$field] ?? $default;
        }
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $msgs) {
            if (is_array($msgs) && $msgs !== []) {
                return (string) $msgs[0];
            }
        }
        return null;
    }
}
