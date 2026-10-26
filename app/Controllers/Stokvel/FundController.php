<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Exceptions\FinanceException;
use App\Services\Finance\FundService;
use App\Support\MoneyMath;

final class FundController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('funds.manage');
        $sid = $this->requireStokvel();
        /** @var FundService $funds */
        $funds = app()->container()->get(FundService::class);
        $funds->ensureDefaults($sid);

        $rows = $this->db()->fetchAll(
            'SELECT * FROM stokvel_funds WHERE stokvel_id = :sid ORDER BY display_order ASC, name ASC',
            ['sid' => $sid]
        );
        foreach ($rows as &$row) {
            $summary = $funds->summary($sid, (int) $row['id']);
            $row['recorded_balance'] = $summary['balance'];
            $row['collected'] = $summary['collected'];
            $row['spent'] = $summary['spent'];
        }
        unset($row);

        return $this->render('stokvel/funds/index', [
            'title' => 'Funds',
            'activeNav' => 'funds',
            'navContext' => 'app',
            'funds' => $rows,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $this->requirePermission('funds.manage');
        $sid = $this->requireStokvel();
        $fund = $this->findFund($sid, (int) $id);
        if (!$fund) {
            Session::flash('error', 'Fund not found.');
            return $this->redirect('/app/funds');
        }

        /** @var FundService $funds */
        $funds = app()->container()->get(FundService::class);
        $summary = $funds->summary($sid, (int) $fund['id']);

        $page = $this->page($request);
        $perPage = $this->perPage($request);
        $paginator = Paginator::fromQuery(
            $this->db(),
            'SELECT COUNT(*) AS c FROM fund_transactions WHERE stokvel_id = :sid AND fund_id = :fid',
            'SELECT ft.*,
                    TRIM(CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\'))) AS created_by_name
             FROM fund_transactions ft
             LEFT JOIN users u ON u.id = ft.created_by_user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE ft.stokvel_id = :sid AND ft.fund_id = :fid
             ORDER BY ft.entry_date DESC, ft.id DESC',
            ['sid' => $sid, 'fid' => (int) $fund['id']],
            $page,
            $perPage,
            url('/app/funds/' . (int) $fund['id'])
        );

        return $this->render('stokvel/funds/show', [
            'title' => (string) ($fund['name'] ?? 'Fund'),
            'activeNav' => 'funds',
            'navContext' => 'app',
            'fund' => $fund,
            'summary' => $summary,
            'paginator' => $paginator,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->requirePermission('funds.manage');
        $this->requireStokvel();
        return $this->render('stokvel/funds/create', [
            'title' => 'New fund',
            'activeNav' => 'funds',
            'navContext' => 'app',
        ]);
    }

    public function store(Request $request): Response
    {
        $this->requirePermission('funds.manage');
        $sid = $this->requireStokvel();
        $data = [
            'code' => strtolower(preg_replace('/[^a-z0-9_]+/i', '_', trim((string) $request->input('code', ''))) ?? ''),
            'name' => trim((string) $request->input('name', '')),
            'description' => trim((string) $request->input('description', '')),
        ];
        $validator = Validator::make($data, [
            'code' => 'required|min:2',
            'name' => 'required|min:2',
        ]);
        if ($validator->fails()) {
            $this->flashOld($request);
            Session::flash('error', 'Code and name are required.');
            return $this->redirect('/app/funds/create');
        }

        $now = now_utc();
        $order = (int) ($this->db()->fetch(
            'SELECT COALESCE(MAX(display_order), 0) AS m FROM stokvel_funds WHERE stokvel_id = :sid',
            ['sid' => $sid]
        )['m'] ?? 0) + 1;

        try {
            $id = $this->db()->insert(
                'INSERT INTO stokvel_funds
                    (stokvel_id, code, name, description, is_system, allow_negative, display_order, is_active, created_at, updated_at)
                 VALUES
                    (:sid, :code, :name, :desc, 0, 0, :ord, 1, :ca, :ua)',
                [
                    'sid' => $sid,
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'desc' => $data['description'] !== '' ? $data['description'] : null,
                    'ord' => $order,
                    'ca' => $now,
                    'ua' => $now,
                ]
            );
            Session::flash('success', 'Fund created.');
            return $this->redirect('/app/funds/' . $id);
        } catch (\Throwable) {
            $this->flashOld($request);
            Session::flash('error', 'Could not create fund (duplicate code?).');
            return $this->redirect('/app/funds/create');
        }
    }

    public function creditForm(Request $request, string $id): Response
    {
        $this->requirePermission('funds.manage');
        $sid = $this->requireStokvel();
        $fund = $this->findFund($sid, (int) $id);
        if (!$fund) {
            return $this->redirect('/app/funds');
        }
        return $this->render('stokvel/funds/credit', [
            'title' => 'Credit fund',
            'activeNav' => 'funds',
            'navContext' => 'app',
            'fund' => $fund,
        ]);
    }

    public function creditStore(Request $request, string $id): Response
    {
        $this->requirePermission('funds.manage');
        $sid = $this->requireStokvel();
        $fund = $this->findFund($sid, (int) $id);
        if (!$fund) {
            return $this->redirect('/app/funds');
        }

        $amount = (string) $request->input('amount', '');
        $description = trim((string) $request->input('description', ''));
        $entryDate = (string) $request->input('entry_date', date('Y-m-d'));
        $reference = trim((string) $request->input('reference', ''));

        if ($description === '' || !is_numeric($amount) || (float) $amount <= 0) {
            $this->flashOld($request);
            Session::flash('error', 'Amount and description are required.');
            return $this->redirect('/app/funds/' . (int) $id . '/credit');
        }

        try {
            /** @var FundService $funds */
            $funds = app()->container()->get(FundService::class);
            $funds->credit($sid, (int) $fund['id'], MoneyMath::normalize($amount), (int) $this->userId(), [
                'description' => $description,
                'entry_date' => $entryDate,
                'reference' => $reference !== '' ? $reference : null,
                'source_type' => 'manual_credit',
            ]);
            Session::flash('success', 'Fund credited.');
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/funds/' . (int) $id . '/credit');
        }

        return $this->redirect('/app/funds/' . (int) $id);
    }

    public function debitForm(Request $request, string $id): Response
    {
        $this->requirePermission('funds.manage');
        $sid = $this->requireStokvel();
        $fund = $this->findFund($sid, (int) $id);
        if (!$fund) {
            return $this->redirect('/app/funds');
        }
        $categories = $this->db()->fetchAll(
            'SELECT id, name FROM expense_categories WHERE stokvel_id = :sid AND is_active = 1 ORDER BY name ASC',
            ['sid' => $sid]
        );
        return $this->render('stokvel/funds/debit', [
            'title' => 'Debit fund (expense)',
            'activeNav' => 'funds',
            'navContext' => 'app',
            'fund' => $fund,
            'categories' => $categories,
        ]);
    }

    public function debitStore(Request $request, string $id): Response
    {
        $this->requirePermission('funds.manage');
        $sid = $this->requireStokvel();
        $fund = $this->findFund($sid, (int) $id);
        if (!$fund) {
            return $this->redirect('/app/funds');
        }

        $amount = (string) $request->input('amount', '');
        $description = trim((string) $request->input('description', ''));
        $entryDate = (string) $request->input('entry_date', date('Y-m-d'));
        $reference = trim((string) $request->input('reference', ''));
        $categoryId = (int) $request->input('expense_category_id', 0);
        $categoryName = trim((string) $request->input('category_name', ''));

        if ($description === '' || !is_numeric($amount) || (float) $amount <= 0) {
            $this->flashOld($request);
            Session::flash('error', 'Amount and description are required.');
            return $this->redirect('/app/funds/' . (int) $id . '/debit');
        }

        if ($categoryId > 0) {
            $cat = $this->db()->fetch(
                'SELECT id, name FROM expense_categories WHERE id = :id AND stokvel_id = :sid LIMIT 1',
                ['id' => $categoryId, 'sid' => $sid]
            );
            if ($cat) {
                $categoryName = (string) $cat['name'];
            } else {
                $categoryId = 0;
            }
        }

        try {
            /** @var FundService $funds */
            $funds = app()->container()->get(FundService::class);
            $funds->debit($sid, (int) $fund['id'], MoneyMath::normalize($amount), (int) $this->userId(), [
                'description' => $description,
                'entry_date' => $entryDate,
                'reference' => $reference !== '' ? $reference : null,
                'category_name' => $categoryName !== '' ? $categoryName : null,
                'expense_category_id' => $categoryId > 0 ? $categoryId : null,
                'source_type' => 'admin_expense',
            ]);
            Session::flash('success', 'Fund debited.');
        } catch (FinanceException $e) {
            $this->flashOld($request);
            Session::flash('error', $e->getMessage());
            return $this->redirect('/app/funds/' . (int) $id . '/debit');
        }

        return $this->redirect('/app/funds/' . (int) $id);
    }

    private function findFund(int $stokvelId, int $id): ?array
    {
        return $this->db()->fetch(
            'SELECT * FROM stokvel_funds WHERE id = :id AND stokvel_id = :sid LIMIT 1',
            ['id' => $id, 'sid' => $stokvelId]
        );
    }
}
