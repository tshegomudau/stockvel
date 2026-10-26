<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class LoanPurposeController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $this->ensureDefaults($sid);
        $q = trim((string) $request->query('q', ''));
        $where = 'stokvel_id = :sid';
        $params = ['sid' => $sid];
        if ($q !== '') {
            $where .= ' AND (name LIKE :q_name OR code LIKE :q_code)';
            $like = '%' . $q . '%';
            $params['q_name'] = $like;
            $params['q_code'] = $like;
        }
        $purposes = $this->db()->fetchAll(
            'SELECT * FROM loan_purposes WHERE ' . $where . ' ORDER BY sort_order ASC, name ASC',
            $params
        );

        return $this->render('stokvel/loans/purposes/index', [
            'title' => 'Loan purposes',
            'activeNav' => 'loans',
            'navContext' => 'app',
            'purposes' => $purposes,
            'filterQ' => $q,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $data = [
            'code' => strtolower(preg_replace('/[^a-z0-9_]+/i', '_', trim((string) $request->input('code', ''))) ?? ''),
            'name' => trim((string) $request->input('name', '')),
            'sort_order' => (int) $request->input('sort_order', 100),
        ];
        $validator = Validator::make($data, [
            'code' => 'required|min:2',
            'name' => 'required|min:2',
        ]);
        if ($validator->fails()) {
            Session::flash('error', 'Purpose code and name are required.');
            return $this->redirect('/app/loans/purposes');
        }
        $now = now_utc();
        try {
            $this->db()->insert(
                'INSERT INTO loan_purposes (stokvel_id, code, name, is_active, sort_order, created_at, updated_at)
                 VALUES (:sid, :code, :name, 1, :sort, :ca, :ua)',
                [
                    'sid' => $sid,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'sort' => $data['sort_order'],
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
            Session::flash('success', 'Loan purpose created.');
        } catch (\Throwable) {
            Session::flash('error', 'Could not create purpose (duplicate code?).');
        }

        return $this->redirect('/app/loans/purposes');
    }

    public function update(Request $request, string $id): Response
    {
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $name = trim((string) $request->input('name', ''));
        $sortOrder = (int) $request->input('sort_order', 0);
        if ($name === '') {
            Session::flash('error', 'Name is required.');
            return $this->redirect('/app/loans/purposes');
        }
        $this->db()->exec(
            'UPDATE loan_purposes SET name = :name, sort_order = :sort, updated_at = :ua
             WHERE id = :id AND stokvel_id = :sid',
            ['name' => $name, 'sort' => $sortOrder, 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', 'Loan purpose updated.');

        return $this->redirect('/app/loans/purposes');
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
        $this->requirePermission('loans.manage');
        $sid = $this->requireStokvel();
        $this->db()->exec(
            'UPDATE loan_purposes SET is_active = :active, updated_at = :ua WHERE id = :id AND stokvel_id = :sid',
            ['active' => $active ? 1 : 0, 'ua' => now_utc(), 'id' => (int) $id, 'sid' => $sid]
        );
        Session::flash('success', $active ? 'Purpose reactivated.' : 'Purpose deactivated.');

        return $this->redirect('/app/loans/purposes');
    }

    private function ensureDefaults(int $stokvelId): void
    {
        $count = (int) ($this->db()->fetch(
            'SELECT COUNT(*) AS c FROM loan_purposes WHERE stokvel_id = :sid',
            ['sid' => $stokvelId]
        )['c'] ?? 0);
        if ($count > 0) {
            return;
        }
        $now = now_utc();
        $starters = [
            ['emergency', 'Emergency', 10],
            ['education', 'Education', 20],
            ['medical', 'Medical', 30],
            ['business', 'Business', 40],
            ['home', 'Home improvement', 50],
            ['other', 'Other', 90],
        ];
        foreach ($starters as [$code, $name, $sort]) {
            $this->db()->insert(
                'INSERT INTO loan_purposes (stokvel_id, code, name, is_active, sort_order, created_at, updated_at)
                 VALUES (:sid, :code, :name, 1, :sort, :ca, :ua)',
                ['sid' => $stokvelId, 'code' => $code, 'name' => $name, 'sort' => $sort, 'ca' => $now, 'ua' => $now]
            );
        }
    }
}
