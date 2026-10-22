<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class ExpenseCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        $categories = $this->db()->fetchAll(
            'SELECT * FROM expense_categories WHERE stokvel_id = :sid ORDER BY name ASC',
            ['sid' => $sid]
        );
        return $this->render('stokvel/expenses/categories', [
            'title' => 'Expense categories',
            'activeNav' => 'expenses',
            'navContext' => 'app',
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        $data = [
            'code' => strtolower(trim((string) $request->input('code', ''))),
            'name' => trim((string) $request->input('name', '')),
        ];
        $validator = Validator::make($data, [
            'code' => 'required|min:2',
            'name' => 'required|min:2',
        ]);
        if ($validator->fails()) {
            Session::flash('error', 'Category code and name are required.');
            return $this->redirect('/app/expenses/categories');
        }
        $now = now_utc();
        try {
            $this->db()->insert(
                'INSERT INTO expense_categories (stokvel_id, code, name, created_at, updated_at)
                 VALUES (:sid, :code, :name, :ca, :ua)',
                ['sid' => $sid, 'code' => $data['code'], 'name' => $data['name'], 'ca' => $now, 'ua' => $now]
            );
            Session::flash('success', 'Category created.');
        } catch (\Throwable) {
            Session::flash('error', 'Could not create category (duplicate code?).');
        }
        return $this->redirect('/app/expenses/categories');
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'Name is required.');
            return $this->redirect('/app/expenses/categories');
        }
        $this->db()->exec(
            'UPDATE expense_categories SET name = :name, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['name' => $name, 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Category updated.');
        return $this->redirect('/app/expenses/categories');
    }

    public function destroy(Request $request, string $id): Response
    {
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        $inUse = $this->db()->fetch(
            'SELECT COUNT(*) AS c FROM expenses WHERE stokvel_id = :sid AND category_id = :id',
            ['sid' => $sid, 'id' => (int) $id]
        );
        if ((int) ($inUse['c'] ?? 0) > 0) {
            $this->db()->exec(
                'UPDATE expense_categories SET is_active = 0, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
                ['ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
            );
            Session::flash('success', 'Category is in use — deactivated instead of deleted.');
            return $this->redirect('/app/expenses/categories');
        }
        $this->db()->exec(
            'DELETE FROM expense_categories WHERE id = :id AND stokvel_id = :sid',
            ['id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Category deleted.');
        return $this->redirect('/app/expenses/categories');
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
        $this->requirePermission('expenses.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE expense_categories SET is_active = :active, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['active' => $active ? 1 : 0, 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', $active ? 'Category reactivated.' : 'Category deactivated.');
        return $this->redirect('/app/expenses/categories');
    }
}
