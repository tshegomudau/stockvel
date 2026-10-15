<?php

declare(strict_types=1);

namespace App\Database;

use App\Core\Database;

final class Seeder
{
    public function __construct(
        private readonly Database $db,
        private readonly string $seedersPath
    ) {
    }

    public function run(?string $class = null): void
    {
        if ($class !== null) {
            $this->runClass($class);
            return;
        }
        $this->runClass('DemoSeeder');
    }

    private function runClass(string $class): void
    {
        $file = $this->seedersPath . '/' . $class . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Seeder not found: {$class}");
        }
        $seeder = require $file;
        if (!is_object($seeder) || !method_exists($seeder, 'run')) {
            throw new \RuntimeException("Invalid seeder: {$class}");
        }
        $seeder->run($this->db);
    }
}
