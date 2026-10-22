<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Auth\AuthService;
use Tests\Support\HttpClient;
use Tests\TestRunner;

/**
 * Support ticket create validation and cross-tenant isolation.
 */
return static function (TestRunner $runner): void {
    $loginAs = static function (string $email, string $slug = 'ubuntu-savings'): AuthService {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login($email, 'Password123!');
        $stokvel = test_db()->fetch('SELECT id FROM stokvels WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
        test_assert($stokvel !== null, 'Missing stokvel ' . $slug);
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        return $auth;
    };

    $runner->add('Valid support ticket create redirects with success', function () use ($loginAs): void {
        $auth = $loginAs('sipho.ndlovu@example.co.za');
        $client = new HttpClient();
        $suffix = bin2hex(random_bytes(4));
        $subject = 'Login button stuck ' . $suffix;
        $res = $client->post('/app/support', [
            'subject' => $subject,
            'category' => 'bug',
            'priority' => 'normal',
            'body' => 'When I click save on contributions nothing happens. Ref ' . $suffix,
        ]);
        test_assert($res['status'] === 302, 'Create should redirect, got ' . $res['status'] . ' ' . substr($res['body'], 0, 200));
        $location = (string) ($res['location'] ?? '');
        test_assert(
            preg_match('#/app/support/(\d+)#', $location, $m) === 1,
            'Expected redirect to ticket show, got ' . $location
        );
        $ticketId = (int) $m[1];
        $row = test_db()->fetch('SELECT * FROM support_tickets WHERE id = :id LIMIT 1', ['id' => $ticketId]);
        test_assert($row !== null, 'Ticket not persisted');
        test_assert((string) $row['subject'] === $subject);
        test_assert((string) $row['status'] === 'open');

        $show = $client->get('/app/support/' . $ticketId);
        test_assert($show['status'] === 200, 'Show status ' . $show['status']);
        test_assert(
            str_contains($show['body'], $subject)
            || str_contains($show['body'], 'Ticket #' . $ticketId),
            'Show missing ticket content'
        );
        $auth->logout();
    }, 'http');

    $runner->add('Missing subject shows field error on support create', function () use ($loginAs): void {
        $auth = $loginAs('sipho.ndlovu@example.co.za');
        $client = new HttpClient();
        $res = $client->post('/app/support', [
            'subject' => '',
            'category' => 'bug',
            'priority' => 'normal',
            'body' => 'Enough detail describing the issue for validation.',
        ]);
        test_assert($res['status'] === 302, 'Invalid create should redirect, got ' . $res['status']);
        $create = $client->get('/app/support/create');
        test_assert($create['status'] === 200);
        test_assert(
            str_contains($create['body'], 'form-error')
            && (
                str_contains($create['body'], 'subject')
                || str_contains($create['body'], 'Please enter a short subject')
            ),
            'Expected field-level subject error'
        );
        $auth->logout();
    }, 'http');

    $runner->add('Unauthorized cross-tenant support ticket show is denied', function () use ($loginAs): void {
        $ubuntuAuth = $loginAs('sipho.ndlovu@example.co.za', 'ubuntu-savings');
        $client = new HttpClient();
        $create = $client->post('/app/support', [
            'subject' => 'Cross tenant isolation ' . bin2hex(random_bytes(3)),
            'category' => 'support',
            'priority' => 'low',
            'body' => 'This ticket belongs to Ubuntu savings only.',
        ]);
        test_assert(preg_match('#/app/support/(\d+)#', (string) ($create['location'] ?? ''), $m) === 1);
        $ticketId = (int) $m[1];
        $ubuntuAuth->logout();

        $masaAuth = $loginAs('grace.mthembu@example.co.za', 'masakhane-burial');
        $client2 = new HttpClient();
        $show = $client2->get('/app/support/' . $ticketId);
        test_assert(
            in_array($show['status'], [302, 403], true),
            'Cross-tenant show should be denied, got ' . $show['status']
        );
        if ($show['status'] === 302) {
            $loc = (string) ($show['location'] ?? '');
            test_assert(
                str_contains($loc, '/app/support') && !str_contains($loc, '/app/support/' . $ticketId),
                'Denied show should redirect away from ticket, got ' . $loc
            );
        }
        test_assert(
            !str_contains($show['body'], 'This ticket belongs to Ubuntu'),
            'Cross-tenant response must not leak ticket body'
        );
        $masaAuth->logout();
    }, 'http');

    $runner->add('Chairperson settings save and roles page load', function () use ($loginAs): void {
        $auth = $loginAs('thabo.mokoena@example.co.za');
        $client = new HttpClient();

        $roles = $client->get('/app/settings/roles');
        test_assert($roles['status'] === 200, 'Roles page status ' . $roles['status'] . ' ' . substr($roles['body'], 0, 200));
        test_assert(
            str_contains($roles['body'], 'Roles') || str_contains($roles['body'], 'permissions'),
            'Roles page missing expected content'
        );
        test_assert(
            !str_contains(strtolower($roles['body']), "doesn't exist")
            && !str_contains(strtolower($roles['body']), 'unknown column'),
            'Roles page has SQL error'
        );

        $stokvel = test_db()->fetch("SELECT id, name FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        test_assert($stokvel !== null);
        $originalName = (string) $stokvel['name'];
        $marker = ' SettingsE2E' . substr(bin2hex(random_bytes(2)), 0, 4);
        $newName = $originalName . $marker;

        $save = $client->post('/app/settings', [
            '_method' => 'PUT',
            'name' => $newName,
            'registration_number' => '',
            'description' => 'E2E settings save check',
            'stokvel_type' => 'general_savings',
            'contribution_frequency' => 'monthly',
            'payment_due_day' => '1',
            'minimum_contribution' => '100',
            'grace_period_days' => '7',
            'arrears_after_days' => '7',
            'year_end_month' => '12',
            'payout_mode' => 'rotational',
            'loans_enabled' => '1',
            'branding_primary_colour' => '#14532d',
        ]);
        test_assert($save['status'] === 302, 'Settings save should redirect, got ' . $save['status'] . ' ' . substr($save['body'], 0, 200));
        test_assert(
            str_contains((string) ($save['location'] ?? ''), '/app/settings'),
            'Settings should redirect back to settings'
        );

        $updated = test_db()->fetch('SELECT name FROM stokvels WHERE id = :id LIMIT 1', ['id' => (int) $stokvel['id']]);
        test_assert((string) ($updated['name'] ?? '') === $newName, 'Settings name not persisted');

        // Restore original name so other tests stay stable.
        test_db()->exec(
            'UPDATE stokvels SET name = :name, updated_at = :ua WHERE id = :id',
            ['name' => $originalName, 'ua' => now_utc(), 'id' => (int) $stokvel['id']]
        );

        $auth->logout();
    }, 'http');
};
