<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Auth\AuthService;
use Tests\Support\HttpClient;
use Tests\TestRunner;

/**
 * Rules create/edit workflow as Ubuntu secretary (seed:e2e / demo seed).
 */
return static function (TestRunner $runner): void {
    $loginSecretary = static function (): AuthService {
        $auth = App::getInstance()->container()->get(AuthService::class);
        $auth->logout();
        $user = $auth->login('sipho.ndlovu@example.co.za', 'Password123!');
        $stokvel = test_db()->fetch("SELECT id FROM stokvels WHERE slug = 'ubuntu-savings' LIMIT 1");
        test_assert($stokvel !== null, 'Ubuntu savings stokvel required');
        $auth->switchStokvel((int) $stokvel['id'], (int) $user['id']);
        return $auth;
    };

    $runner->add('Secretary can create a rule with body visible on show', function () use ($loginSecretary): void {
        $auth = $loginSecretary();
        $client = new HttpClient();
        $suffix = bin2hex(random_bytes(4));
        $title = 'E2E Rule ' . $suffix;
        $body = 'Members must attend at least one meeting per quarter. Ref ' . $suffix;

        $res = $client->post('/app/rules', [
            'title' => $title,
            'body' => $body,
            'category' => 'meetings',
        ]);
        test_assert($res['status'] === 302, 'Create should redirect, got ' . $res['status'] . ' ' . substr($res['body'], 0, 200));
        $location = (string) ($res['location'] ?? '');
        test_assert(
            preg_match('#/app/rules/(\d+)#', $location, $m) === 1,
            'Expected redirect to rule show, got ' . $location
        );
        $ruleId = (int) $m[1];

        $row = test_db()->fetch(
            'SELECT r.id, r.title, r.category, rv.content
             FROM rules r
             INNER JOIN rule_versions rv ON rv.rule_id = r.id AND rv.stokvel_id = r.stokvel_id
             WHERE r.id = :id
             ORDER BY rv.version_number DESC LIMIT 1',
            ['id' => $ruleId]
        );
        test_assert($row !== null, 'Rule row missing after create');
        test_assert((string) $row['title'] === $title, 'Title not saved');
        test_assert((string) $row['content'] === $body, 'Body not saved to rule_versions');
        test_assert((string) $row['category'] === 'meetings', 'Category not saved');

        $show = $client->get('/app/rules/' . $ruleId);
        test_assert($show['status'] === 200, 'Show status ' . $show['status']);
        test_assert(str_contains($show['body'], $title), 'Show missing title');
        test_assert(str_contains($show['body'], 'attend at least one meeting'), 'Show missing rule body');

        $auth->logout();
    }, 'http');

    $runner->add('Secretary create with missing title shows field error', function () use ($loginSecretary): void {
        $auth = $loginSecretary();
        $client = new HttpClient();
        $res = $client->post('/app/rules', [
            'title' => '',
            'body' => 'Valid rule body text for validation test.',
            'category' => 'general',
        ]);
        test_assert($res['status'] === 302, 'Invalid create should redirect back, got ' . $res['status']);
        $create = $client->get('/app/rules/create');
        test_assert($create['status'] === 200, 'Create form status ' . $create['status']);
        test_assert(
            str_contains($create['body'], 'form-error')
            && (str_contains($create['body'], 'title') || str_contains($create['body'], 'Please enter a rule title')),
            'Expected field-level title error on create form'
        );
        $auth->logout();
    }, 'http');

    $runner->add('Secretary can edit a rule and publish a new version', function () use ($loginSecretary): void {
        $auth = $loginSecretary();
        $client = new HttpClient();
        $suffix = bin2hex(random_bytes(4));
        $create = $client->post('/app/rules', [
            'title' => 'Editable Rule ' . $suffix,
            'body' => 'Original rule text version one ' . $suffix,
            'category' => 'general',
        ]);
        test_assert(preg_match('#/app/rules/(\d+)#', (string) ($create['location'] ?? ''), $m) === 1);
        $ruleId = (int) $m[1];

        $updatedBody = 'Updated rule text version two ' . $suffix;
        $update = $client->post('/app/rules/' . $ruleId, [
            '_method' => 'PUT',
            'title' => 'Editable Rule Updated ' . $suffix,
            'body' => $updatedBody,
            'category' => 'constitution',
        ]);
        test_assert($update['status'] === 302, 'Update should redirect, got ' . $update['status']);
        test_assert(
            str_contains((string) ($update['location'] ?? ''), '/app/rules/' . $ruleId),
            'Update should land on show'
        );

        $versions = test_db()->fetchAll(
            'SELECT version_number, content FROM rule_versions WHERE rule_id = :id ORDER BY version_number ASC',
            ['id' => $ruleId]
        );
        test_assert(count($versions) >= 2, 'Expected at least two versions after edit');
        $latest = $versions[count($versions) - 1];
        test_assert((string) $latest['content'] === $updatedBody, 'Latest version content mismatch');

        $show = $client->get('/app/rules/' . $ruleId);
        test_assert($show['status'] === 200);
        test_assert(str_contains($show['body'], 'Updated rule text version two'), 'Show missing updated body');
        test_assert(str_contains($show['body'], 'Editable Rule Updated'), 'Show missing updated title');

        $auth->logout();
    }, 'http');

    $runner->add('Secretary edit with empty body shows field error', function () use ($loginSecretary): void {
        $auth = $loginSecretary();
        $client = new HttpClient();
        $create = $client->post('/app/rules', [
            'title' => 'Validation Target ' . bin2hex(random_bytes(3)),
            'body' => 'Enough characters for a valid body.',
            'category' => 'general',
        ]);
        test_assert(preg_match('#/app/rules/(\d+)#', (string) ($create['location'] ?? ''), $m) === 1);
        $ruleId = (int) $m[1];

        $res = $client->post('/app/rules/' . $ruleId, [
            '_method' => 'PUT',
            'title' => 'Still a title',
            'body' => '',
            'category' => 'general',
        ]);
        test_assert($res['status'] === 302, 'Invalid update should redirect, got ' . $res['status']);
        $edit = $client->get('/app/rules/' . $ruleId . '/edit');
        test_assert($edit['status'] === 200);
        test_assert(
            str_contains($edit['body'], 'form-error')
            && (str_contains($edit['body'], 'rule text') || str_contains($edit['body'], 'Please enter the rule text')),
            'Expected field-level body error on edit form'
        );
        $auth->logout();
    }, 'http');
};
