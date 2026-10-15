<?php

declare(strict_types=1);

namespace Tests;

final class TestRunner
{
    /** @var list<array{name: string, callable: callable}> */
    private array $cases = [];

    public function __construct(private readonly string $testsPath)
    {
    }

    public function add(string $name, callable $test): void
    {
        $this->cases[] = ['name' => $name, 'callable' => $test];
    }

    public function run(): bool
    {
        require_once $this->testsPath . '/bootstrap.php';

        foreach (glob($this->testsPath . '/*Test.php') ?: [] as $file) {
            $register = require $file;
            if (is_callable($register)) {
                $register($this);
            }
        }

        $failed = 0;
        $passed = 0;
        foreach ($this->cases as $case) {
            try {
                ($case['callable'])();
                echo "[PASS] {$case['name']}\n";
                $passed++;
            } catch (\Throwable $e) {
                echo "[FAIL] {$case['name']}: {$e->getMessage()}\n";
                $failed++;
            }
        }

        echo "\n{$passed} passed, {$failed} failed.\n";
        return $failed === 0;
    }
}
