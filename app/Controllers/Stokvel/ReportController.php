<?php

declare(strict_types=1);

namespace App\Controllers\Stokvel;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;

final class ReportController extends Controller
{
    /** @var array<string, array{title: string, permission: string}> */
    private const REPORTS = [
        'contributions' => ['title' => 'Contributions', 'permission' => 'reports.view'],
        'arrears' => ['title' => 'Arrears', 'permission' => 'reports.view'],
        'members' => ['title' => 'Membership', 'permission' => 'reports.view'],
        'expenses' => ['title' => 'Expenses', 'permission' => 'reports.view'],
        'loans' => ['title' => 'Loans', 'permission' => 'reports.view'],
        'payouts' => ['title' => 'Payouts', 'permission' => 'reports.view'],
        'ledger' => ['title' => 'General ledger', 'permission' => 'reports.view'],
        'cashflow' => ['title' => 'Cash flow', 'permission' => 'reports.view'],
        'meetings' => ['title' => 'Meetings', 'permission' => 'reports.view'],
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

    public function show(Request $request, string $type): Response
    {
        if (!isset(self::REPORTS[$type])) {
            return $this->redirect('/app/reports');
        }
        $this->requirePermission(self::REPORTS[$type]['permission']);
        $sid = $this->requireStokvel();
        $from = (string) $request->query('from', date('Y-m-01'));
        $to = (string) $request->query('to', date('Y-m-d'));

        $result = $this->fetchReportRows($type, $sid, $from, $to);

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

        $dataRows = $this->fetchReportRows($type, $sid, $from, $to);

        if ($dataRows === []) {
            return Response::csv($type . '-report.csv', ['message'], [['No data for selected period']]);
        }

        $headers = array_keys($dataRows[0]);
        $body = array_map(static fn ($row) => array_values($row), $dataRows);

        return Response::csv($type . '-' . date('Y-m-d') . '.csv', $headers, $body);
    }

    private function fetchReportRows(string $type, int $sid, string $from, string $to): array
    {
        return $this->service(\App\Services\Reports\ReportService::class, fn ($db) => new class($db) {
            public function __construct(private $db) {}
            public function run(string $type, int $stokvelId, string $from, string $to): array
            {
                return match ($type) {
                    'contributions' => $this->db->fetchAll(
                        'SELECT * FROM contributions WHERE stokvel_id = :sid AND paid_at BETWEEN :f AND :t ORDER BY paid_at',
                        ['sid' => $stokvelId, 'f' => $from, 't' => $to]
                    ),
                    'arrears' => $this->db->fetchAll(
                        'SELECT * FROM member_arrears WHERE stokvel_id = :sid ORDER BY balance_due DESC',
                        ['sid' => $stokvelId]
                    ),
                    'members' => $this->db->fetchAll(
                        'SELECT sm.*, p.first_name, p.last_name, u.email FROM memberships sm JOIN users u ON u.id = sm.user_id LEFT JOIN user_profiles p ON p.user_id = u.id WHERE sm.stokvel_id = :sid',
                        ['sid' => $stokvelId]
                    ),
                    'expenses' => $this->db->fetchAll(
                        'SELECT * FROM expenses WHERE stokvel_id = :sid AND expense_date BETWEEN :f AND :t',
                        ['sid' => $stokvelId, 'f' => $from, 't' => $to]
                    ),
                    'loans' => $this->db->fetchAll('SELECT * FROM loans WHERE stokvel_id = :sid', ['sid' => $stokvelId]),
                    'payouts' => $this->db->fetchAll('SELECT * FROM payouts WHERE stokvel_id = :sid', ['sid' => $stokvelId]),
                    'ledger' => $this->db->fetchAll(
                        'SELECT jl.* FROM journal_lines jl JOIN journal_entries je ON je.id = jl.journal_entry_id WHERE je.stokvel_id = :sid AND je.entry_date BETWEEN :f AND :t',
                        ['sid' => $stokvelId, 'f' => $from, 't' => $to]
                    ),
                    'cashflow' => $this->db->fetchAll(
                        "SELECT DATE(paid_at) AS day, SUM(amount) AS total FROM contributions WHERE stokvel_id = :sid AND status = 'posted' AND paid_at BETWEEN :f AND :t GROUP BY DATE(paid_at)",
                        ['sid' => $stokvelId, 'f' => $from, 't' => $to]
                    ),
                    'meetings' => $this->db->fetchAll(
                        'SELECT * FROM meetings WHERE stokvel_id = :sid AND scheduled_at BETWEEN :f AND :t',
                        ['sid' => $stokvelId, 'f' => $from . ' 00:00:00', 't' => $to . ' 23:59:59']
                    ),
                    default => [],
                };
            }
        })->run($type, $sid, $from, $to);
    }
}
