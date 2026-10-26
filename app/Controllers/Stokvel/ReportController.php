<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Analytics\TrendsService;
use App\Services\Reports\ReportService;

final class ReportController extends Controller
{
    /** @var array<string, array{title: string, permission: string}> */
    private const REPORTS = [
        'contributions' => ['title' => 'Contribution summary', 'permission' => 'reports.view'],
        'arrears' => ['title' => 'Arrears', 'permission' => 'reports.view'],
        'members' => ['title' => 'Member register', 'permission' => 'reports.view'],
        'expenses' => ['title' => 'Expenses', 'permission' => 'reports.view'],
        'loans' => ['title' => 'Loans outstanding', 'permission' => 'reports.view'],
        'payouts' => ['title' => 'Payout summary', 'permission' => 'reports.view'],
        'ledger' => ['title' => 'Transaction statement', 'permission' => 'reports.view'],
        'cashflow' => ['title' => 'Cash / ledger movement', 'permission' => 'reports.view'],
        'meetings' => ['title' => 'Meeting attendance', 'permission' => 'reports.view'],
    ];

    public function index(Request $request): Response
    {
        $this->requirePermission('reports.view');
        return $this->render('stokvel/reports/index', [
            'title' => 'Reports',
            'activeNav' => 'reports',
            'navContext' => 'app',
            'reports' => self::REPORTS,
        ]);
    }

    public function trends(Request $request): Response
    {
        $this->requirePermission('reports.view');
        $sid = $this->requireStokvel();
        /** @var TrendsService $trends */
        $trends = app()->container()->get(TrendsService::class);
        $data = $trends->monthlySeries($sid, 6);

        return $this->render('stokvel/reports/trends', [
            'title' => 'Trends & forecasting',
            'activeNav' => 'reports',
            'navContext' => 'app',
            'series' => $data,
        ]);
    }

    public function annual(Request $request): Response
    {
        $this->requirePermission('reports.view');
        $sid = $this->requireStokvel();

        $startMonth = $this->financialYearStartMonth($sid);
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Africa/Johannesburg'));
        $year = (int) $today->format('Y');
        $month = (int) $today->format('n');
        if ($month < $startMonth) {
            $year--;
        }
        $from = sprintf('%04d-%02d-01', $year, $startMonth);
        $fyStart = new \DateTimeImmutable($from, new \DateTimeZone('Africa/Johannesburg'));
        $to = $fyStart->modify('+1 year -1 day')->format('Y-m-d');
        if ($to > $today->format('Y-m-d')) {
            $to = $today->format('Y-m-d');
        }

        $from = (string) $request->query('from', $from);
        $to = (string) $request->query('to', $to);

        /** @var TrendsService $trends */
        $trends = app()->container()->get(TrendsService::class);
        $summary = $trends->annualSummary($sid, $from, $to);
        $layout = $request->query('print') ? 'layouts/print' : 'layouts/app';

        return $this->render('stokvel/reports/annual', [
            'title' => 'Annual management summary',
            'activeNav' => 'reports',
            'navContext' => 'app',
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
        ], $layout);
    }

    public function show(Request $request, string $type): Response
    {
        if (!isset(self::REPORTS[$type])) {
            return $this->redirect('/app/reports');
        }
        $this->requirePermission(self::REPORTS[$type]['permission']);
        $sid = $this->requireStokvel();
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));

        $result = $this->presentExportRows($this->fetchReportRows($type, $sid, $from, $to));

        $layout = $request->query('print') ? 'layouts/print' : 'layouts/app';

        return $this->render('stokvel/reports/show', [
            'title' => self::REPORTS[$type]['title'],
            'activeNav' => 'reports',
            'navContext' => 'app',
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'rows' => $result,
            'reportTitle' => self::REPORTS[$type]['title'],
        ], $layout);
    }

    public function export(Request $request, string $type): Response
    {
        if (!isset(self::REPORTS[$type])) {
            return $this->redirect('/app/reports');
        }
        $this->requirePermission('reports.export');
        $sid = $this->requireStokvel();
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));

        $dataRows = $this->presentExportRows($this->fetchReportRows($type, $sid, $from, $to));

        if ($dataRows === []) {
            return Response::csv($type . '-report.csv', ['Message'], [['No data for selected period']]);
        }

        $headers = array_keys($dataRows[0]);
        $body = array_map(static fn ($row) => array_values($row), $dataRows);

        return Response::csv($type . '-' . date('Y-m-d') . '.csv', $headers, $body);
    }

    /**
     * Map internal column names to business-friendly export headings.
     * Drops internal IDs and sensitive/metadata fields.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function presentExportRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $drop = [
            'id', 'stokvel_id', 'user_id', 'membership_id', 'created_by_user_id',
            'updated_by_user_id', 'deleted_at', 'password', 'password_hash',
            'remember_token', 'token_hash', 'id_number_encrypted', 'id_number_hash',
        ];
        $labels = [
            'member_number' => 'Member number',
            'first_name' => 'First name',
            'last_name' => 'Last name',
            'email' => 'Email',
            'mobile' => 'Mobile',
            'amount' => 'Amount',
            'status' => 'Status',
            'paid_at' => 'Payment date',
            'payment_method' => 'Payment method',
            'category' => 'Category',
            'category_name' => 'Category',
            'description' => 'Description',
            'incurred_at' => 'Expense date',
            'scheduled_at' => 'Scheduled',
            'title' => 'Title',
            'principal_amount' => 'Principal',
            'outstanding_balance' => 'Outstanding',
            'requested_amount' => 'Requested amount',
            'joined_at' => 'Joined',
            'entry_date' => 'Date',
            'account_code' => 'Account code',
            'account_name' => 'Account',
            'debit' => 'Debit',
            'credit' => 'Credit',
        ];

        $out = [];
        foreach ($rows as $row) {
            $mapped = [];
            foreach ($row as $key => $value) {
                $key = (string) $key;
                if (in_array($key, $drop, true) || str_ends_with($key, '_id')) {
                    continue;
                }
                $label = $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
                $mapped[$label] = is_scalar($value) || $value === null ? $value : json_encode($value);
            }
            if ($mapped !== []) {
                $out[] = $mapped;
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function fetchReportRows(string $type, int $sid, string $from, string $to): array
    {
        /** @var ReportService $reports */
        $reports = app()->container()->get(ReportService::class);

        return $reports->run($type, $sid, $from, $to);
    }

    /**
     * Financial year start month from configured year-end (settings), falling back to stokvels column.
     */
    private function financialYearStartMonth(int $stokvelId): int
    {
        $settings = $this->db()->fetch(
            'SELECT year_end_month FROM stokvel_settings WHERE stokvel_id = :sid LIMIT 1',
            ['sid' => $stokvelId]
        );
        $yearEnd = (int) ($settings['year_end_month'] ?? 0);
        if ($yearEnd >= 1 && $yearEnd <= 12) {
            return ($yearEnd % 12) + 1;
        }
        $stokvel = $this->db()->fetch(
            'SELECT financial_year_start_month FROM stokvels WHERE id = :id LIMIT 1',
            ['id' => $stokvelId]
        );
        $start = (int) ($stokvel['financial_year_start_month'] ?? 1);

        return ($start >= 1 && $start <= 12) ? $start : 1;
    }
}
