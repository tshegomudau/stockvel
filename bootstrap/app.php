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

Env::load($basePath . '/.env');

date_default_timezone_set(env('APP_TIMEZONE', 'Africa/Johannesburg'));

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
