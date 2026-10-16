<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Container;
use App\Core\Env;
use App\Core\ExceptionHandler;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Services\Auth\AuthService;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

$envName = Env::resolveNameFromArgv($_SERVER['argv'] ?? [])
    ?? ($_ENV['STOCKVEL_ENV'] ?? $_SERVER['STOCKVEL_ENV'] ?? null);

if ($envName === 'testing' || ($_ENV['APP_ENV'] ?? '') === 'testing') {
    Env::load($basePath . '/.env', false);
    Env::load($basePath . '/.env.testing', true);
    $_ENV['APP_ENV'] = 'testing';
    $_SERVER['APP_ENV'] = 'testing';
    putenv('APP_ENV=testing');
} else {
    Env::load($basePath . '/.env', true);
    if (is_file($basePath . '/.env.local')) {
        Env::load($basePath . '/.env.local', true);
    }
}

date_default_timezone_set(env('APP_TIMEZONE', 'Africa/Johannesburg'));

if (env('APP_ENV') === 'testing') {
    error_reporting(E_ALL);
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
}

$appConfig = require $basePath . '/config/app.php';
$dbConfig = require $basePath . '/config/database.php';

$container = new Container();
($register = require $basePath . '/bootstrap/register.php')($container, $basePath, $dbConfig);

$container->singleton(ExceptionHandler::class, static fn (Container $c) => new ExceptionHandler($c->get(\App\Core\Logger::class)));

$app = new App($container, $basePath);
$app->setConfig([
    'app' => $appConfig,
    'database' => $dbConfig,
]);

View::setBasePath($basePath . '/app/Views');

Session::start();

$container->get(AuthService::class)->hydrateTenantFromSession();

$router = $container->get(Router::class);
require $basePath . '/routes/web.php';

return $app;
