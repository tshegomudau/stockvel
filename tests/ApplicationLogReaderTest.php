<?php

declare(strict_types=1);

use App\Services\Platform\ApplicationLogReader;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('Application log reader includes deploy logs and filters by date', function (): void {
        $dir = sys_get_temp_dir() . '/stokvel-logreader-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        try {
            file_put_contents(
                $dir . '/deploy-20261111.log',
                "[2026-11-11 08:00:00 SAST] ===== Deployment started =====\n\n"
                . "[2026-11-11 08:01:00 SAST] START: git pull\n"
                . "[2026-11-11 08:05:00 SAST] ERROR: command failed at line 12\n"
            );
            file_put_contents(
                $dir . '/deploy-20261110.log',
                "[2026-11-10 18:00:00 SAST] ===== Deployment started =====\n"
            );
            file_put_contents(
                $dir . '/app-2026-11-11.log',
                json_encode(['ts' => '2026-11-11 09:00:00', 'level' => 'INFO', 'message' => 'app boot'], JSON_THROW_ON_ERROR) . "\n"
            );

            $reader = new ApplicationLogReader($dir);

            $all = $reader->search(['kind' => 'deploy'], 1, 50);
            test_assert($all['total'] >= 3, 'Expected deploy lines, got ' . $all['total']);
            $files = array_unique(array_map(static fn ($r) => (string) ($r['source_file'] ?? ''), $all['items']));
            test_assert(in_array('deploy-20261111.log', $files, true), 'Must include deploy-20261111.log');

            $day = $reader->search([
                'kind' => 'deploy',
                'date_from' => '2026-11-11',
                'date_to' => '2026-11-11',
            ], 1, 50);
            test_assert($day['total'] >= 2, 'Date filter should keep 11 Nov deploy lines');
            foreach ($day['items'] as $row) {
                test_assert(str_starts_with((string) $row['ts'], '2026-11-11'), 'Filtered row ts ' . ($row['ts'] ?? ''));
                test_assert((string) ($row['source'] ?? '') === 'deploy');
            }

            $errors = $reader->search([
                'kind' => 'deploy',
                'level' => 'ERROR',
                'date_from' => '2026-11-11',
                'date_to' => '2026-11-11',
            ], 1, 50);
            test_assert($errors['total'] === 1, 'Expected one ERROR deploy line');
            test_assert(str_contains((string) ($errors['items'][0]['message'] ?? ''), 'command failed'));

            $appOnly = $reader->search(['kind' => 'application', 'date_from' => '2026-11-11', 'date_to' => '2026-11-11'], 1, 50);
            test_assert($appOnly['total'] === 1, 'Application kind should exclude deploy files');
        } finally {
            foreach (glob($dir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }, 'unit');
};
