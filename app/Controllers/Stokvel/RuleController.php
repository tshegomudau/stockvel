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

        $status = trim((string) $request->query('status', ''));
        $category = trim((string) $request->query('category', ''));
        $q = trim((string) $request->query('q', ''));
        $allowedStatuses = ['active', 'inactive'];
        $categoryMap = $this->ruleCategoryMap($sid);

        $where = 'stokvel_id = :sid';
        $params = ['sid' => $sid];
        if ($status !== '' && in_array($status, $allowedStatuses, true)) {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }
        if ($category !== '' && isset($categoryMap[$category])) {
            $where .= ' AND category = :category';
            $params['category'] = $category;
        }
        if ($q !== '') {
            $where .= ' AND (title LIKE :q_title OR code LIKE :q_code)';
            $like = '%' . $q . '%';
            $params['q_title'] = $like;
            $params['q_code'] = $like;
        }

        $filterQuery = array_filter([
            'status' => $status !== '' && in_array($status, $allowedStatuses, true) ? $status : null,
            'category' => $category !== '' && isset($categoryMap[$category]) ? $category : null,
            'q' => $q !== '' ? $q : null,
        ], static fn ($v) => $v !== null);

        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM rules WHERE ' . $where,
            'SELECT * FROM rules WHERE ' . $where . ' ORDER BY created_at DESC',
            $params,
            $page,
            $perPage,
            url('/app/rules'),
            $filterQuery
        );
        return $this->render('stokvel/rules/index', [
            'title' => 'Rules',
            'activeNav' => 'rules',
            'navContext' => 'app',
            'paginator' => $paginator,
            'filterStatus' => $status !== '' && in_array($status, $allowedStatuses, true) ? $status : '',
            'filterCategory' => $category !== '' && isset($categoryMap[$category]) ? $category : '',
            'filterQ' => $q,
            'statusOptions' => $allowedStatuses,
            'categoryOptions' => $categoryMap,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('rules.manage');
        $sid = $this->requireStokvel();
        return $this->render('stokvel/rules/create', [
            'title' => 'New Rule',
            'activeNav' => 'rules',
            'navContext' => 'app',
            'categories' => $this->ruleCategoryMap($sid),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('rules.manage');
        $sid = $this->requireStokvel();
        $categoryMap = $this->ruleCategoryMap($sid);
        $data = [
            'title' => trim((string) $request->input('title', '')),
            'body' => trim((string) $request->input('body', '')),
            'category' => trim((string) $request->input('category', 'general')) ?: 'general',
        ];
        if (!isset($categoryMap[$data['category']])) {
            $data['category'] = array_key_first($categoryMap) ?: 'general';
        }
        $validator = Validator::make($data, [
            'title' => 'required|string|min:3|max:255',
            'body' => 'required|string|min:5',
            'category' => 'required|string',
        ], [
            'title.required' => 'Please enter a rule title.',
            'title.min' => 'Title must be at least 3 characters.',
            'title.max' => 'Title must be at most 255 characters.',
            'body.required' => 'Please enter the rule text.',
            'body.min' => 'Rule text must be at least 5 characters.',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            $this->flashErrors($validator->errors());
            Session::flash('error', $validator->firstError() ?? 'Please complete the rule details.');
            return $this->redirect('/app/rules/create');
        }

        try {
            /** @var RuleService $rules */
            $rules = app()->container()->get(RuleService::class);
            $ruleId = $rules->create($sid, (int) $this->userId(), $data);
        } catch (\Throwable $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not save rule. Check permissions and try again.');
            return $this->redirect('/app/rules/create');
        }

        Session::flash('success', 'Rule saved.');
        return $this->redirect('/app/rules/' . $ruleId);
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
            'categories' => $this->ruleCategoryMap($sid),
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('rules.manage');
        $sid = $this->requireStokvel();
        $existing = $this->db()->fetch(
            'SELECT id FROM rules WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$existing) {
            Session::flash('error', 'Rule not found.');
            return $this->redirect('/app/rules');
        }

        $categoryMap = $this->ruleCategoryMap($sid);
        $data = [
            'title' => trim((string) $request->input('title', '')),
            'body' => trim((string) $request->input('body', '')),
            'category' => trim((string) $request->input('category', 'general')) ?: 'general',
        ];
        if (!isset($categoryMap[$data['category']])) {
            $data['category'] = array_key_first($categoryMap) ?: 'general';
        }
        $validator = Validator::make($data, [
            'title' => 'required|string|min:3|max:255',
            'body' => 'required|string|min:5',
            'category' => 'required|string',
        ], [
            'title.required' => 'Please enter a rule title.',
            'title.min' => 'Title must be at least 3 characters.',
            'title.max' => 'Title must be at most 255 characters.',
            'body.required' => 'Please enter the rule text.',
            'body.min' => 'Rule text must be at least 5 characters.',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            $this->flashErrors($validator->errors());
            Session::flash('error', $validator->firstError() ?? 'Please complete the rule details.');
            return $this->redirect('/app/rules/' . $id . '/edit');
        }

        try {
            $this->db()->exec(
                'UPDATE rules SET title = :title, category = :category, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
                [
                    'title' => $data['title'],
                    'category' => $data['category'],
                    'ua' => now_utc(),
                    'id' => (int) $id,
                    'sid' => $sid,
                ]
            );
            /** @var RuleService $rules */
            $rules = app()->container()->get(RuleService::class);
            $rules->createNewVersion($sid, (int) $id, (int) $this->userId(), $data['body']);
        } catch (\Throwable $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not update rule.');
            return $this->redirect('/app/rules/' . $id . '/edit');
        }

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

    /**
     * @return array<string, string> code => name
     */
    private function ruleCategoryMap(int $stokvelId): array
    {
        $fallback = [
            'general' => 'General',
            'constitution' => 'Constitution',
            'contributions' => 'Contributions',
            'loans' => 'Loans',
            'meetings' => 'Meetings',
            'payouts' => 'Payouts',
            'discipline' => 'Discipline',
            'other' => 'Other',
        ];
        try {
            $rows = $this->db()->fetchAll(
                'SELECT code, name FROM rule_categories
                 WHERE stokvel_id = :sid AND is_active = 1
                 ORDER BY name ASC',
                ['sid' => $stokvelId]
            );
            if ($rows === []) {
                return $fallback;
            }
            $map = [];
            foreach ($rows as $row) {
                $code = (string) ($row['code'] ?? '');
                if ($code !== '') {
                    $map[$code] = (string) ($row['name'] ?? $code);
                }
            }
            return $map !== [] ? $map : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
