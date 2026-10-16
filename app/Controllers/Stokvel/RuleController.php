<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\Rules\RuleService;

final class RuleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('rules.manage');
        $sid = $this->requireStokvel();
        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM rules WHERE stokvel_id = :sid',
            'SELECT * FROM rules WHERE stokvel_id = :sid ORDER BY created_at DESC',
            ['sid' => $sid],
            $page,
            $perPage,
            url('/app/rules')
        );
        return $this->render('stokvel/rules/index', [
            'title' => 'Rules',
            'activeNav' => 'rules',
            'navContext' => 'app',
            'paginator' => $paginator,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('rules.manage');
        return $this->render('stokvel/rules/create', [
            'title' => 'New Rule',
            'activeNav' => 'rules',
            'navContext' => 'app',
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('rules.manage');
        $sid = $this->requireStokvel();
        $data = [
            'title' => trim((string) $request->input('title', '')),
            'body' => trim((string) $request->input('body', '')),
            'category' => trim((string) $request->input('category', 'general')),
        ];
        $validator = Validator::make($data, [
            'title' => 'required|string',
            'body' => 'required|string',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Invalid rule details.');
            return $this->redirect('/app/rules/create');
        }

        /** @var RuleService $rules */
        $rules = app()->container()->get(RuleService::class);
        $rules->create($sid, (int) $this->userId(), $data);

        Session::flash('success', 'Rule saved.');
        return $this->redirect('/app/rules');
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('rules.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch(
            'SELECT * FROM rules WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$row) {
            return $this->redirect('/app/rules');
        }
        $versions = $this->db()->fetchAll(
            'SELECT * FROM rule_versions WHERE rule_id = :id AND stokvel_id = :sid ORDER BY version_number DESC',
            ['id' => (int) $id, 'sid' => $sid]
        );
        return $this->render('stokvel/rules/show', [
            'title' => 'Rule #' . $id,
            'activeNav' => 'rules',
            'navContext' => 'app',
            'item' => $row,
            'versions' => $versions,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('rules.manage');
        $sid = $this->requireStokvel();
        $row = $this->db()->fetch(
            'SELECT * FROM rules WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$row) {
            return $this->redirect('/app/rules');
        }
        $latest = $this->db()->fetch(
            'SELECT content FROM rule_versions WHERE rule_id = :id AND stokvel_id = :sid
             ORDER BY version_number DESC LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        $row['body'] = $latest['content'] ?? '';
        return $this->render('stokvel/rules/edit', [
            'title' => 'Edit Rule',
            'activeNav' => 'rules',
            'navContext' => 'app',
            'item' => $row,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('rules.manage');
        $sid = $this->requireStokvel();
        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));
        if ($title === '' || $body === '') {
            Session::flash('error', 'Title and body are required.');
            return $this->redirect('/app/rules/' . $id . '/edit');
        }

        $this->db()->exec(
            'UPDATE rules SET title = :title, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['title' => $title, 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        /** @var RuleService $rules */
        $rules = app()->container()->get(RuleService::class);
        $rules->createNewVersion($sid, (int) $id, (int) $this->userId(), $body);

        Session::flash('success', 'Rule updated.');
        return $this->redirect('/app/rules/' . $id);
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('rules.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE rules SET status = :status, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['status' => 'inactive', 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Rule removed.');
        return $this->redirect('/app/rules');
    }
}
