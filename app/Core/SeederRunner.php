<?php

declare(strict_types=1);

namespace App\Core;

use Database\Seeders\DemoSeeder;
use PDO;
use RuntimeException;

final class SeederRunner
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function runDemo(): void
    {
        $path = dirname(__DIR__, 2) . '/database/seeders/DemoSeeder.php';
        if (!is_file($path)) {
            throw new RuntimeException('Demo seeder not found at ' . $path);
        }

        require_once $path;

        $seeder = new DemoSeeder();
        $seeder->run($this->pdo);
    }
}
