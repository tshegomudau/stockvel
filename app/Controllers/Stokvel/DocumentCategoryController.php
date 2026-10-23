<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class DocumentCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('documents.manage_categories');
        $sid = $this->requireStokvel();
        $q = trim((string) $request->query('q', ''));
        $where = 'stokvel_id = :sid';
        $params = ['sid' => $sid];
        if ($q !== '') {
            $where .= ' AND (name LIKE :q_name OR code LIKE :q_code)';
            $like = '%' . $q . '%';
            $params['q_name'] = $like;
            $params['q_code'] = $like;
        }
        $categories = $this->db()->fetchAll(
            'SELECT * FROM document_categories WHERE ' . $where . ' ORDER BY name ASC',
            $params
        );

        return $this->render('stokvel/documents/categories/index', [
            'title' => 'Document categories',
            'activeNav' => 'documents',
            'navContext' => 'app',
            'categories' => $categories,
            'filterQ' => $q,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('documents.manage_categories');

        return $this->render('stokvel/documents/categories/create', [
            'title' => 'New document category',
            'activeNav' => 'documents',
            'navContext' => 'app',
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('documents.manage_categories');
        $sid = $this->requireStokvel();
        $data = [
            'code' => strtolower(preg_replace('/[^a-z0-9_]+/i', '_', trim((string) $request->input('code', ''))) ?? ''),
            'name' => trim((string) $request->input('name', '')),
        ];
        $validator = Validator::make($data, [
            'code' => 'required|min:2',
            'name' => 'required|min:2',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Code and name are required.');
            return $this->redirect('/app/documents/categories/create');
        }

        $now = now_utc();
        try {
            $this->db()->insert(
                'INSERT INTO document_categories (stokvel_id, code, name, is_active, created_at, updated_at)
                 VALUES (:sid, :code, :name, 1, :ca, :ua)',
                ['sid' => $sid, 'code' => $data['code'], 'name' => $data['name'], 'ca' => $now, 'ua' => $now]
            );
            Session::flash('success', 'Document category created.');
        } catch (\Throwable) {
            Session::flash('error', 'Could not create category (duplicate code?).');
            return $this->redirect('/app/documents/categories/create');
        }

        return $this->redirect('/app/documents/categories');
    }

    public function edit(Request $request, string $id): Response
    {
        $this->requirePermission('documents.manage_categories');
        $sid = $this->requireStokvel();
        $category = $this->db()->fetch(
            'SELECT * FROM document_categories WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => (int) $id, 'sid' => $sid]
        );
        if (!$category) {
            return $this->redirect('/app/documents/categories');
        }

        return $this->render('stokvel/documents/categories/edit', [
            'title' => 'Edit document category',
            'activeNav' => 'documents',
            'navContext' => 'app',
            'category' => $category,
        ]);
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('documents.manage_categories');
        $sid = $this->requireStokvel();
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Name is required.');
            return $this->redirect('/app/documents/categories/' . $id . '/edit');
        }

        $this->db()->exec(
            'UPDATE document_categories SET name = :name, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['name' => $name, 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Category updated.');
        return $this->redirect('/app/documents/categories');
    }

    public function activate(Request $request, string $id): Response
    {
        return $this->setActive($id, true);
    }

    public function deactivate(Request $request, string $id): Response
    {
        return $this->setActive($id, false);
    }

    private function setActive(string $id, bool $active): Response
    {
        $this->requirePermission('documents.manage_categories');
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE document_categories SET is_active = :active, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['active' => $active ? 1 : 0, 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', $active ? 'Category activated.' : 'Category deactivated.');
        return $this->redirect('/app/documents/categories');
    }
}
