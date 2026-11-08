<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Session;
use App\Services\Auth\AuthService;
use Tests\Support\HttpClient;
use Tests\TestRunner;

/**
 * UI regression coverage for shared form styling, overflow-safe CSS, and key public flows.
 * Viewports documented: desktop 1280×800, tablet 768×1024, mobile 390×844.
 */
return static function (TestRunner $runner): void {
    $viewports = [
        'desktop' => ['width' => 1280, 'height' => 800],
        'tablet' => ['width' => 768, 'height' => 1024],
        'mobile' => ['width' => 390, 'height' => 844],
    ];

    $assertStyledControls = static function (string $html, string $pageLabel): void {
        if (!preg_match_all('/<(input|select|textarea)\b([^>]*)>/i', $html, $matches, PREG_SET_ORDER)) {
            return;
        }
        $skipTypes = ['hidden', 'checkbox', 'radio', 'submit', 'button', 'image', 'reset'];
        foreach ($matches as $m) {
            $attrs = $m[2];
            $type = null;
            if (preg_match('/\btype\s*=\s*["\']?([^"\'\s>]+)/i', $attrs, $tm)) {
                $type = strtolower($tm[1]);
            }
            if ($type !== null && in_array($type, $skipTypes, true)) {
                continue;
            }
            test_assert(
                (bool) preg_match('/\bform-control\b/', $attrs),
                $pageLabel . ' control missing form-control class: <' . $m[1] . $attrs . '>'
            );
            if (strtolower($m[1]) === 'input' && $type === null) {
                test_assert(false, $pageLabel . ' input missing type attribute: ' . $attrs);
            }
        }
    };

    $runner->add('Shared form CSS defines form-control and overflow-safe widths', function () use ($viewports): void {
        $forms = file_get_contents(dirname(__DIR__) . '/public/assets/css/forms.css');
        $responsive = file_get_contents(dirname(__DIR__) . '/public/assets/css/responsive.css');
        test_assert(is_string($forms) && $forms !== '');
        test_assert(is_string($responsive) && $responsive !== '');

        test_assert(str_contains($forms, '.form-control'), 'Missing .form-control');
        test_assert(str_contains($forms, '.form-select'), 'Missing .form-select');
        test_assert(str_contains($forms, '.form-textarea'), 'Missing .form-textarea');
        test_assert(str_contains($forms, '.form-check'), 'Missing .form-check alias');
        test_assert(str_contains($forms, 'input:not([type])'), 'Untyped inputs must be styled');
        test_assert(str_contains($forms, 'datetime-local'), 'datetime-local must be styled');
        test_assert(str_contains($forms, 'input[type="file"]'), 'file inputs must be styled');
        test_assert(str_contains($forms, 'max-width: 100%'), 'Controls need max-width 100%');
        test_assert(str_contains($forms, 'box-sizing: border-box'), 'Controls need border-box');
        test_assert(str_contains($forms, 'min-width: 0'), 'Form groups need min-width 0 for grid overflow');
        test_assert(str_contains($forms, 'overflow-wrap: anywhere'), 'Labels/errors must wrap');

        test_assert(str_contains($responsive, 'max-width: 959px'), 'Tablet breakpoint missing');
        test_assert(str_contains($responsive, 'max-width: 480px'), 'Mobile breakpoint missing');
        test_assert(str_contains($responsive, '.modal'), 'Modal narrow-viewport rules missing');
        test_assert(str_contains($responsive, '.table-wrap'), 'Table wrap rules missing');
        test_assert(str_contains($responsive, 'overflow-x: clip'), 'Horizontal overflow guard missing');

        // Document checked viewports for reporting / future browser harness.
        foreach ($viewports as $name => $size) {
            test_assert($size['width'] > 0 && $size['height'] > 0, 'Viewport ' . $name . ' invalid');
        }
        $artifact = dirname(__DIR__) . '/storage/test-artifacts/ui-viewports.json';
        file_put_contents($artifact, json_encode([
            'viewports' => $viewports,
            'checked_at' => gmdate('c'),
            'notes' => 'CSS asserts width containment; HTTP pages checked for form-control classes',
        ], JSON_PRETTY_PRINT));
    }, 'ui');

    $runner->add('View templates apply form-control to visible form controls', function (): void {
        $root = dirname(__DIR__) . '/app/Views';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $skipTypes = ['hidden', 'checkbox', 'radio', 'submit', 'button', 'image', 'reset'];
        $missing = [];
        $untyped = [];
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            if (!preg_match_all('/<(input|select|textarea)\b([^>]*)>/i', $src, $matches, PREG_SET_ORDER)) {
                continue;
            }
            $rel = substr($file->getPathname(), strlen(dirname(__DIR__)) + 1);
            foreach ($matches as $m) {
                $attrs = $m[2];
                $type = null;
                if (preg_match('/\btype\s*=\s*["\']?([^"\'\s>]+)/i', $attrs, $tm)) {
                    $type = strtolower($tm[1]);
                }
                if ($type !== null && in_array($type, $skipTypes, true)) {
                    continue;
                }
                if (!preg_match('/\bform-control\b/', $attrs)) {
                    $missing[] = $rel . ' :: <' . $m[1] . '>';
                }
                if (strtolower($m[1]) === 'input' && $type === null) {
                    $untyped[] = $rel;
                }
            }
        }
        test_assert($missing === [], 'Controls missing form-control: ' . implode('; ', array_slice($missing, 0, 8)));
        test_assert($untyped === [], 'Untyped inputs remain: ' . implode('; ', array_slice($untyped, 0, 8)));
    }, 'ui');

    $runner->add('Public auth and stokvel pages render styled form controls', function () use ($assertStyledControls): void {
        Session::forget('user_id');
        Session::forget('active_stokvel_id');
        $client = new HttpClient();
        foreach (['/login', '/register', '/forgot-password', '/start-stokvel'] as $path) {
            $res = $client->get($path);
            test_assert(in_array($res['status'], [200, 302], true), $path . ' status ' . $res['status']);
            if ($res['status'] === 200) {
                test_assert(str_contains($res['body'], 'forms.css') || str_contains($res['body'], 'form-control'), $path . ' missing form styles');
                $assertStyledControls($res['body'], $path);
                test_assert(
                    (bool) preg_match('/<(label|legend)[^>]*>/i', $res['body']),
                    $path . ' should expose accessible labels'
                );
            }
        }
        $register = $client->get('/register');
        test_assert($register['status'] === 200, 'Register page required for password strength UI');
        test_assert(str_contains($register['body'], 'data-password-policy'), 'Register must expose password policy config');
        test_assert(str_contains($register['body'], 'data-password-strength'), 'Register must show password strength meter');
        test_assert(str_contains($register['body'], 'password-requirements'), 'Register must list password requirements');
    }, 'ui');

    $runner->add('Member profile, stokvel settings, and platform audit forms use form-control', function () use ($assertStyledControls): void {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $client = new HttpClient();

        $member = $auth->login('lerato.pillay@example.co.za', 'Password123!');
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        test_assert($stokvel !== null);
        $auth->switchStokvel((int) $stokvel['id'], (int) $member['id']);
        $profile = $client->get('/member/profile');
        test_assert($profile['status'] === 200, 'Member profile status ' . $profile['status']);
        $assertStyledControls($profile['body'], '/member/profile');
        $auth->logout();

        $chair = $auth->login('thabo.mokoena@example.co.za', 'Password123!');
        $auth->switchStokvel((int) $stokvel['id'], (int) $chair['id']);
        $settings = $client->get('/app/settings');
        test_assert(in_array($settings['status'], [200, 302], true), 'Settings status ' . $settings['status']);
        if ($settings['status'] === 200) {
            $assertStyledControls($settings['body'], '/app/settings');
        }
        $auth->logout();

        $auth->login('admin@stockvel.local', 'Password123!');
        $audit = $client->get('/platform/audit');
        test_assert($audit['status'] === 200, 'Audit status ' . $audit['status']);
        $assertStyledControls($audit['body'], '/platform/audit');
        $tenants = $client->get('/platform/tenants/create');
        test_assert(in_array($tenants['status'], [200, 302], true), 'Tenant create status ' . $tenants['status']);
        if ($tenants['status'] === 200) {
            $assertStyledControls($tenants['body'], '/platform/tenants/create');
        }
        $auth->logout();
    }, 'ui');

    $runner->add('Long labels and validation errors remain in alert containers without breaking markup', function (): void {
        Session::forget('user_id');
        $client = new HttpClient();
        $res = $client->post('/login', [
            'email' => 'not-an-email',
            'password' => 'short',
        ]);
        test_assert(in_array($res['status'], [302, 200], true), 'Validation post status ' . $res['status']);

        // Follow redirect to see flash errors.
        $page = $client->get('/login');
        test_assert($page['status'] === 200, 'Login re-render status ' . $page['status']);
        test_assert(
            str_contains($page['body'], 'alert') || str_contains($page['body'], 'error'),
            'Validation feedback missing'
        );
        test_assert(!str_contains($page['body'], 'style="position:absolute;left:-9999'), 'Errors must stay visible');
        // Ensure form controls still present and styled after error state.
        test_assert(str_contains($page['body'], 'form-control'), 'Styled controls missing after validation error');
        test_assert(
            (bool) preg_match('/class="[^"]*alert[^"]*"/', $page['body']),
            'Validation should use alert container classes'
        );
    }, 'ui');

    $runner->add('Dashboard tables and modals CSS remain usable at narrow widths', function (): void {
        $tables = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/tables.css');
        $components = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/components.css');
        $responsive = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/responsive.css');

        test_assert(str_contains($tables, 'overflow-x: auto'), 'Tables must scroll horizontally inside wrap');
        test_assert(str_contains($tables, '.table-wrap'), 'table-wrap required');
        test_assert(str_contains($tables, 'max-width: 719px') || str_contains($tables, 'mobile-cards'), 'Mobile card fallback required');
        test_assert(str_contains($components, '.modal'), 'Modal component missing');
        test_assert(str_contains($components, 'width: min(32rem, 100%)'), 'Modal must cap to viewport');
        test_assert(str_contains($responsive, 'max-height: calc(100vh - 2rem)') || str_contains($responsive, '.modal'), 'Modal overflow at narrow width');

        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login('nomsa.dlamini@example.co.za', 'Password123!');
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        $client = new HttpClient();
        $members = $client->get('/app/members');
        test_assert($members['status'] === 200, 'Members status ' . $members['status']);
        test_assert(
            str_contains($members['body'], 'table-wrap') || str_contains($members['body'], 'mobile-card'),
            'Members layout missing responsive table/card container'
        );
        $auth->logout();
    }, 'ui');

    $runner->add('Registration join path reaches get-started without 403 layout failure', function (): void {
        Session::forget('user_id');
        Session::forget('active_stokvel_id');
        $client = new HttpClient();
        $email = 'ui_join_' . bin2hex(random_bytes(3)) . '@example.com';
        $res = $client->post('/register', [
            'intent' => 'join_stokvel',
            'first_name' => 'UiJoin',
            'last_name' => 'Tester',
            'email' => $email,
            'mobile' => '+27821234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'consent' => '1',
        ]);
        test_assert(in_array($res['status'], [302, 200], true), 'Register status ' . $res['status']);
        $loc = (string) ($res['location'] ?? '');
        test_assert(
            $loc === '' || !str_contains($loc, '403'),
            'Register redirected to error: ' . $loc
        );

        // Landing after register (verify notice or get-started).
        $next = $client->get($loc !== '' ? parse_url($loc, PHP_URL_PATH) ?: '/account/get-started' : '/account/get-started');
        if (in_array($next['status'], [302], true) && !empty($next['location'])) {
            $path = parse_url((string) $next['location'], PHP_URL_PATH) ?: '/account/get-started';
            $next = $client->get((string) $path);
        }
        test_assert(in_array($next['status'], [200, 302], true), 'Post-register page status ' . $next['status']);
        if ($next['status'] === 200) {
            test_assert(
                !str_contains($next['body'], 'No active stokvel selected'),
                'Post-register still shows active-stokvel 403'
            );
            test_assert(
                str_contains($next['body'], 'form-control')
                || str_contains($next['body'], 'get-started')
                || str_contains($next['body'], 'invitation')
                || str_contains($next['body'], 'Verify')
                || str_contains($next['body'], 'Welcome')
                || str_contains($next['body'], 'stokvel'),
                'Unexpected post-register body'
            );
        }
    }, 'ui');

    $runner->add('Stokvel creation guest flow submits and shows status page without layout failure', function () use ($assertStyledControls): void {
        Session::forget('user_id');
        Session::forget('active_stokvel_id');
        $client = new HttpClient();
        $form = $client->get('/start-stokvel');
        test_assert($form['status'] === 200, 'Start form status ' . $form['status']);
        $assertStyledControls($form['body'], '/start-stokvel');

        $email = 'ui_create_' . bin2hex(random_bytes(3)) . '@example.com';
        $name = 'UI Create Circle ' . substr(bin2hex(random_bytes(2)), 0, 4);
        $res = $client->post('/start-stokvel', [
            'first_name' => 'Guest',
            'last_name' => 'Requester',
            'email' => $email,
            'stokvel_name' => $name,
            'stokvel_type' => 'general_savings',
            'description' => 'UI regression submission',
        ]);
        test_assert(in_array($res['status'], [302, 200], true), 'Submit status ' . $res['status'] . ' ' . substr($res['body'], 0, 200));
        $loc = (string) ($res['location'] ?? '');
        test_assert(
            str_contains($loc, '/stokvel-requests/') || $res['status'] === 200,
            'Expected status redirect, got ' . $loc
        );
        if (str_contains($loc, '/stokvel-requests/')) {
            $path = parse_url($loc, PHP_URL_PATH) ?: $loc;
            $statusPage = $client->get((string) $path);
            test_assert($statusPage['status'] === 200, 'Status page ' . $statusPage['status']);
            test_assert(
                str_contains(strtolower($statusPage['body']), 'pending')
                || str_contains(strtolower($statusPage['body']), 'request')
                || str_contains(strtolower($statusPage['body']), 'approval'),
                'Status page missing pending/request content'
            );
            test_assert(!str_contains($statusPage['body'], 'No active stokvel selected'));
        }
    }, 'ui');

    $runner->add('CSS media queries cover desktop tablet and mobile containment rules', function () use ($viewports): void {
        $css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/forms.css')
            . (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/responsive.css')
            . (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/layout.css')
            . (string) file_get_contents(dirname(__DIR__) . '/public/assets/css/tables.css');

        // Desktop inherits base max-width:100% / min-width:0 rules (no min-width media required).
        test_assert($viewports['desktop']['width'] === 1280);
        test_assert(str_contains($css, 'max-width: 100%'));
        test_assert(str_contains($css, '@media (max-width: 959px)'));
        test_assert(str_contains($css, '@media (max-width: 480px)') || str_contains($css, '@media (max-width: 719px)'));
        test_assert(str_contains($css, '.filter-bar'));
        test_assert(str_contains($css, '.form-group'));
        // Tablet width 768 falls under 959px rules; mobile 390 under 480/719.
        test_assert($viewports['tablet']['width'] <= 959);
        test_assert($viewports['mobile']['width'] <= 480 || $viewports['mobile']['width'] <= 719);
    }, 'ui');
};
