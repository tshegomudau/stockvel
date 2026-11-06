<?php

declare(strict_types=1);

/**
 * Shared-hosting web installer (no SSH required).
 *
 * 1. Upload the app and create an empty MySQL database in cPanel.
 * 2. Visit /install.php?token=YOUR_TOKEN (token printed by pack script, or set INSTALL_TOKEN).
 * 3. Submit DB + APP settings → migrations (+ optional demo seed).
 * 4. Delete this file via File Manager after success.
 */

$basePath = dirname(__DIR__);
$lockFile = $basePath . '/storage/install.lock';
$tokenFile = $basePath . '/storage/install.token';
$messages = [];
$errors = [];
$done = false;

require_once $basePath . '/bootstrap/autoload.php';

use App\Core\Env;
use App\Core\Migrator;
use App\Core\SeederRunner;

if (is_file($basePath . '/.env')) {
    Env::load($basePath . '/.env', true);
}

$expectedToken = (string) ($_ENV['INSTALL_TOKEN'] ?? '');
if ($expectedToken === '' && is_file($tokenFile)) {
    $expectedToken = trim((string) file_get_contents($tokenFile));
}

function h(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ensure_storage(string $basePath): void
{
    $dirs = [
        'storage/logs',
        'storage/sessions',
        'storage/cache',
        'storage/documents',
        'storage/exports',
        'storage/mail',
        'public/uploads',
    ];
    foreach ($dirs as $relative) {
        $path = $basePath . '/' . $relative;
        if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
            throw new RuntimeException('Cannot create directory: ' . $relative . ' (check File Manager permissions).');
        }
        @chmod($path, 0775);
    }
}

function write_env(string $basePath, array $data): void
{
    $path = $basePath . '/.env';
    $lines = [];
    foreach ($data as $key => $value) {
        $value = (string) $value;
        if ($value !== '' && preg_match('/[\s#"\']/', $value)) {
            $value = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
        }
        $lines[] = $key . '=' . $value;
    }
    if (file_put_contents($path, implode("\n", $lines) . "\n") === false) {
        throw new RuntimeException('Could not write .env — make the app root writable once, or upload .env via FTP.');
    }
    @chmod($path, 0600);
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

$providedToken = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$locked = is_file($lockFile);

$checks = [
    'PHP ≥ 8.3' => version_compare(PHP_VERSION, '8.3.0', '>='),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'openssl' => extension_loaded('openssl'),
    'json' => extension_loaded('json'),
    'fileinfo' => extension_loaded('fileinfo'),
    'bcmath' => extension_loaded('bcmath'),
    'intl (recommended)' => extension_loaded('intl'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    try {
        if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            throw new RuntimeException('Invalid install token.');
        }
        foreach (['PHP ≥ 8.3', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo', 'bcmath'] as $req) {
            if (empty($checks[$req])) {
                throw new RuntimeException('Missing requirement: ' . $req);
            }
        }

        $appUrl = rtrim(trim((string) ($_POST['app_url'] ?? '')), '/');
        $appKey = trim((string) ($_POST['app_key'] ?? ''));
        $dbHost = trim((string) ($_POST['db_host'] ?? 'localhost'));
        $dbPort = trim((string) ($_POST['db_port'] ?? '3306'));
        $dbName = trim((string) ($_POST['db_database'] ?? ''));
        $dbUser = trim((string) ($_POST['db_username'] ?? ''));
        $dbPass = (string) ($_POST['db_password'] ?? '');
        $basePathCfg = trim((string) ($_POST['app_base_path'] ?? ''));
        $seedDemo = !empty($_POST['seed_demo']);
        $cronToken = trim((string) ($_POST['cron_token'] ?? '')) ?: random_token(16);

        if ($appUrl === '' || $dbName === '' || $dbUser === '') {
            throw new RuntimeException('APP_URL, database name, and database username are required.');
        }
        if ($appKey === '' || strlen($appKey) < 32) {
            $appKey = random_token(32);
        }

        ensure_storage($basePath);

        $envData = [
            'APP_NAME' => 'stockvel',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $appUrl,
            'APP_BASE_PATH' => $basePathCfg,
            'ASSET_VERSION' => '1',
            'APP_KEY' => $appKey,
            'DB_HOST' => $dbHost,
            'DB_PORT' => $dbPort !== '' ? $dbPort : '3306',
            'DB_DATABASE' => $dbName,
            'DB_USERNAME' => $dbUser,
            'DB_PASSWORD' => $dbPass,
            'DB_CHARSET' => 'utf8mb4',
            'SESSION_NAME' => 'stockvel_session',
            'SESSION_LIFETIME' => '120',
            'SESSION_SECURE' => str_starts_with($appUrl, 'https') ? 'true' : 'false',
            'SESSION_SAME_SITE' => 'Lax',
            'PASSWORD_ALGO' => 'argon2id',
            'LOGIN_MAX_ATTEMPTS' => '5',
            'LOGIN_LOCKOUT_MINUTES' => '15',
            'CSRF_TOKEN_TTL' => '7200',
            'RATE_LIMIT_WINDOW' => '60',
            'REMEMBER_ME_DAYS' => '30',
            'FORCE_PASSWORD_CHANGE_OUTSIDE_DEV' => 'true',
            'APP_TIMEZONE' => 'Africa/Johannesburg',
            'APP_CURRENCY' => 'ZAR',
            'APP_LOCALE' => 'en_ZA',
            'MAIL_DRIVER' => 'log',
            'MAIL_FROM_ADDRESS' => 'noreply@' . (parse_url($appUrl, PHP_URL_HOST) ?: 'example.com'),
            'MAIL_FROM_NAME' => 'stockvel',
            'MAIL_HOST' => 'mail.smtp2go.com',
            'MAIL_PORT' => '2525',
            'MAIL_USERNAME' => '',
            'MAIL_PASSWORD' => '',
            'MAIL_ENCRYPTION' => 'tls',
            'SMS_DRIVER' => 'log',
            'PAYMENT_DRIVER' => 'mock',
            'PAYMENT_WEBHOOK_SECRET' => random_token(16),
            'UPLOAD_MAX_MB' => '10',
            'DOCUMENT_RETENTION_DAYS' => '2555',
            'CRON_LOCK_TTL' => '300',
            'CRON_TOKEN' => $cronToken,
            'INSTALL_TOKEN' => $expectedToken,
        ];
        write_env($basePath, $envData);
        Env::load($basePath . '/.env', true);

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $dbHost,
            $dbPort !== '' ? $dbPort : '3306',
            $dbName
        );
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $migrator = new Migrator($pdo, $basePath . '/database/migrations');
        $ran = $migrator->migrate();
        $messages[] = 'Migrated ' . count($ran) . ' pending file(s).';

        if ($seedDemo) {
            (new SeederRunner($pdo))->runDemo();
            $messages[] = 'Demo seed completed. Sign in with admin@stockvel.local / Password123! then change passwords.';
        }

        $lockPayload = [
            'installed_at' => gmdate('c'),
            'php' => PHP_VERSION,
            'migrations_ran' => count($ran),
            'seeded' => $seedDemo,
        ];
        file_put_contents($lockFile, json_encode($lockPayload, JSON_PRETTY_PRINT) . "\n");
        if (is_file($tokenFile)) {
            @unlink($tokenFile);
        }

        $messages[] = 'Install lock written. Delete public/install.php in File Manager now.';
        $messages[] = 'Cron URL: ' . $appUrl . ($basePathCfg !== '' ? rtrim($basePathCfg, '/') : '') . '/cron.php?token=' . rawurlencode($cronToken);
        $done = true;
        $locked = true;
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$tokenOk = $expectedToken !== '' && hash_equals($expectedToken, $providedToken);
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'example.com');
$guessUrl = $scheme . '://' . $host;
$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
$guessBase = ($scriptDir === '/' || $scriptDir === '' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>stockvel installer</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 44rem; margin: 2rem auto; padding: 0 1rem; line-height: 1.45; color: #122; }
        h1 { font-size: 1.5rem; }
        .card { border: 1px solid #ccd; border-radius: 8px; padding: 1rem 1.25rem; margin: 1rem 0; background: #fafbfc; }
        .ok { color: #0a5; } .bad { color: #b00; } .warn { color: #a60; }
        label { display: block; font-weight: 600; margin-top: .75rem; }
        input[type=text], input[type=password], input[type=url] { width: 100%; padding: .5rem; box-sizing: border-box; }
        .help { color: #556; font-size: .9rem; margin: .25rem 0 0; }
        button { margin-top: 1rem; padding: .6rem 1rem; font-weight: 600; cursor: pointer; }
        ul { padding-left: 1.2rem; }
        code { background: #eef; padding: .1rem .3rem; border-radius: 3px; }
    </style>
</head>
<body>
<h1>stockvel shared-hosting installer</h1>
<p>No SSH required. Creates <code>.env</code>, storage folders, and runs database migrations.</p>

<?php if ($locked && $done): ?>
    <div class="card">
        <p class="ok"><strong>Installation complete.</strong></p>
        <ul><?php foreach ($messages as $m): ?><li><?= h($m) ?></li><?php endforeach; ?></ul>
        <p class="warn"><strong>Delete <code>public/install.php</code> now</strong> via cPanel File Manager / FTP.</p>
        <p><a href="<?= h(($guessBase !== '' ? $guessBase : '') . '/') ?>">Open the app</a></p>
    </div>
<?php elseif ($locked): ?>
    <div class="card">
        <p class="warn">Already installed (<code>storage/install.lock</code> present).</p>
        <p>Delete <code>public/install.php</code> if it still exists. To re-run migrations later, use a VPS/`bin/console`, or temporarily remove the lock (advanced).</p>
    </div>
<?php elseif ($expectedToken === ''): ?>
    <div class="card">
        <p class="bad">No install token found.</p>
        <p>Either upload <code>storage/install.token</code> (from the pack script) or add <code>INSTALL_TOKEN=...</code> to <code>.env</code>, then open this page with <code>?token=...</code>.</p>
    </div>
<?php elseif (!$tokenOk): ?>
    <div class="card">
        <p class="bad">Missing or invalid token.</p>
        <p>Open <code>/install.php?token=YOUR_TOKEN</code> using the token from the pack script output or <code>INSTALL_TOKEN</code>.</p>
    </div>
<?php else: ?>
    <div class="card">
        <h2>Requirements</h2>
        <ul>
            <?php foreach ($checks as $label => $ok): ?>
                <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= h($label) ?>: <?= $ok ? 'yes' : 'no' ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if ($errors !== []): ?>
        <div class="card">
            <?php foreach ($errors as $err): ?><p class="bad"><?= h($err) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="card">
        <input type="hidden" name="token" value="<?= h($providedToken) ?>">

        <h2>Application</h2>
        <label for="app_url">APP_URL</label>
        <input id="app_url" name="app_url" type="url" required value="<?= h($_POST['app_url'] ?? $guessUrl) ?>">
        <p class="help">Public site URL, e.g. <code>https://yourdomain.co.za</code> (no trailing slash).</p>

        <label for="app_base_path">APP_BASE_PATH (optional)</label>
        <input id="app_base_path" name="app_base_path" type="text" value="<?= h($_POST['app_base_path'] ?? '') ?>" placeholder="leave blank if document root is public/">
        <p class="help">Only if the app lives in a subdirectory of the domain (e.g. <code>/stockvel/public</code>). Prefer pointing the subdomain/docroot at <code>public/</code> instead.</p>

        <label for="app_key">APP_KEY</label>
        <input id="app_key" name="app_key" type="text" value="<?= h($_POST['app_key'] ?? '') ?>" placeholder="leave blank to auto-generate">

        <h2>Database (from cPanel MySQL)</h2>
        <label for="db_host">DB_HOST</label>
        <input id="db_host" name="db_host" type="text" required value="<?= h($_POST['db_host'] ?? 'localhost') ?>">

        <label for="db_port">DB_PORT</label>
        <input id="db_port" name="db_port" type="text" value="<?= h($_POST['db_port'] ?? '3306') ?>">

        <label for="db_database">DB_DATABASE</label>
        <input id="db_database" name="db_database" type="text" required value="<?= h($_POST['db_database'] ?? '') ?>">

        <label for="db_username">DB_USERNAME</label>
        <input id="db_username" name="db_username" type="text" required value="<?= h($_POST['db_username'] ?? '') ?>">

        <label for="db_password">DB_PASSWORD</label>
        <input id="db_password" name="db_password" type="password" value="<?= h($_POST['db_password'] ?? '') ?>">

        <label for="cron_token">CRON_TOKEN</label>
        <input id="cron_token" name="cron_token" type="text" value="<?= h($_POST['cron_token'] ?? '') ?>" placeholder="auto-generate if blank">
        <p class="help">Used by <code>cron.php</code> from a cPanel “Fetch URL” cron job.</p>

        <label><input type="checkbox" name="seed_demo" value="1"<?= !empty($_POST['seed_demo']) ? ' checked' : '' ?>> Seed demo accounts (dev/demo only — skip for real members)</label>

        <button type="submit">Install</button>
    </form>
<?php endif; ?>
</body>
</html>
