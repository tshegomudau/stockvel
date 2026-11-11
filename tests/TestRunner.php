<?php

declare(strict_types=1);

namespace Tests;

use App\Core\App;
use App\Services\Platform\TestFailureNotifier;

final class TestRunner
{
    /** @var list<array{name: string, suite: string, callable: callable}> */
    private array $cases = [];

    public function __construct(private readonly string $testsPath)
    {
    }

    public function add(string $name, callable $test, string $suite = 'integration'): void
    {
        $this->cases[] = ['name' => $name, 'suite' => $suite, 'callable' => $test];
    }

    public function run(string $suite = 'all'): bool
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
        $blocked = 0;
        $executed = [];

        foreach ($this->cases as $case) {
            if ($suite !== 'all' && $case['suite'] !== $suite) {
                continue;
            }
            try {
                ($case['callable'])();
                echo "[PASS] {$case['name']}\n";
                $passed++;
                $executed[] = ['name' => $case['name'], 'status' => 'pass'];
            } catch (BlockedTestException $e) {
                echo "[BLOCKED] {$case['name']}: {$e->getMessage()}\n";
                $blocked++;
                $executed[] = ['name' => $case['name'], 'status' => 'blocked', 'error' => $e->getMessage()];
            } catch (\Throwable $e) {
                echo "[FAIL] {$case['name']}: {$e->getMessage()}\n";
                if (env('APP_DEBUG')) {
                    echo $e->getFile() . ':' . $e->getLine() . "\n";
                }
                $failed++;
                $executed[] = ['name' => $case['name'], 'status' => 'fail', 'error' => $e->getMessage()];
            }
        }

        $report = [
            'suite' => $suite,
            'passed' => $passed,
            'failed' => $failed,
            'blocked' => $blocked,
            'cases' => $executed,
            'database' => \App\Core\TestingGuard::currentDatabaseName(),
            'finished_at' => gmdate('c'),
        ];

        $resultsPath = dirname($this->testsPath) . '/storage/test-artifacts/last-run.json';
        @mkdir(dirname($resultsPath), 0775, true);
        file_put_contents($resultsPath, json_encode($report, JSON_PRETTY_PRINT));

        echo "\n{$passed} passed, {$failed} failed, {$blocked} blocked.\n";

        if ($suite === 'all' && ($failed > 0 || $blocked > 0)) {
            $this->notifySupportOfFailures($report);
        }

        return $failed === 0 && $blocked === 0;
    }

    /** @param array<string, mixed> $report */
    private function notifySupportOfFailures(array $report): void
    {
        try {
            /** @var TestFailureNotifier $notifier */
            $notifier = App::getInstance()->container()->get(TestFailureNotifier::class);
            $sent = $notifier->notifyIfNeeded($report);
            if ($sent) {
                $to = $notifier->resolveSupportEmail() ?? 'support';
                echo "Failure report emailed to {$to}.\n";
            } else {
                echo "Failure report email skipped (disabled or no support email configured).\n";
            }
        } catch (\Throwable $e) {
            echo 'Failure report email error: ' . $e->getMessage() . "\n";
        }
    }
}
