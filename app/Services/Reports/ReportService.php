<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Core\Database;

final class ReportService
{
    public function __construct(
        private readonly Database $db
    ) {
    }

    /** @param array<string, mixed> $filters */
    public function run(string $type, int $stokvelId, string $from, string $to): array
    {
        $filters = ['from' => $from, 'to' => $to];

        return match ($type) {
            'contributions' => $this->contributionSummary($stokvelId, $filters),
            'arrears' => $this->outstandingContributions($stokvelId, $filters),
            'members' => $this->memberRegister($stokvelId, $filters),
            'expenses' => $this->expenseReport($stokvelId, $filters),
            'loans' => $this->loanPortfolio($stokvelId, $filters),
            'payouts' => $this->payoutSchedule($stokvelId, $filters),
            'ledger' => $this->transactionLedger($stokvelId, $filters),
            'cashflow' => $this->cashMovement($stokvelId, $filters),
            'meetings' => $this->meetingAttendance($stokvelId, $filters),
            default => [],
        };
    }

    /** @param array<string, mixed> $filters */
    public function memberRegister(int $stokvelId, array $filters = []): array
    {
        return $this->db->fetchAll(
            'SELECT m.id AS membership_id, m.member_number, m.status, m.joined_at,
                    p.first_name, p.last_name, u.email, u.mobile
             FROM memberships m
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE m.stokvel_id = :stokvel_id
             ORDER BY p.last_name, p.first_name',
            ['stokvel_id' => $stokvelId]
        );
    }

    /** @param array<string, mixed> $filters */
    public function contributionSummary(int $stokvelId, array $filters = []): array
    {
        [$from, $to] = $this->dateRange($filters);

        return $this->db->fetchAll(
            'SELECT pt.transaction_ref AS receipt_number, pt.amount, pt.payment_method, pt.status, pt.paid_at,
                    pt.external_reference AS reference,
                    sm.member_number, p.first_name, p.last_name, u.email
             FROM payment_transactions pt
             LEFT JOIN memberships sm ON sm.id = pt.membership_id AND sm.stokvel_id = pt.stokvel_id
             LEFT JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE pt.stokvel_id = :stokvel_id AND pt.paid_at BETWEEN :from_date AND :to_date
             ORDER BY pt.paid_at DESC',
            ['stokvel_id' => $stokvelId, 'from_date' => $from . ' 00:00:00', 'to_date' => $to . ' 23:59:59']
        );
    }

    /** @param array<string, mixed> $filters */
    public function memberStatement(int $stokvelId, array $filters = []): array
    {
        $memberId = (int) ($filters['member_id'] ?? 0);
        if ($memberId <= 0) {
            return [];
        }

        if ($this->tableExists('member_ledger_entries')) {
            return $this->db->fetchAll(
                'SELECT * FROM member_ledger_entries
                 WHERE stokvel_id = :stokvel_id AND member_id = :member_id
                 ORDER BY entry_date DESC, id DESC',
                ['stokvel_id' => $stokvelId, 'member_id' => $memberId]
            );
        }

        return $this->db->fetchAll(
            'SELECT paid_at AS entry_date, amount, payment_method AS description, transaction_ref AS reference
             FROM payment_transactions
             WHERE stokvel_id = :stokvel_id AND membership_id = :member_id AND status = :status
             ORDER BY paid_at DESC',
            ['stokvel_id' => $stokvelId, 'member_id' => $memberId, 'status' => 'completed']
        );
    }

    /** @param array<string, mixed> $filters */
    public function outstandingContributions(int $stokvelId, array $filters = []): array
    {
        if ($this->tableExists('member_arrears')) {
            return $this->db->fetchAll(
                'SELECT * FROM member_arrears WHERE stokvel_id = :stokvel_id ORDER BY balance_due DESC',
                ['stokvel_id' => $stokvelId]
            );
        }

        return $this->db->fetchAll(
            'SELECT mc.charge_code, mc.description, mc.amount, mc.amount_paid, mc.due_date, mc.status,
                    (mc.amount - mc.amount_paid) AS balance_due,
                    sm.member_number, p.first_name, p.last_name, u.email
             FROM member_charges mc
             LEFT JOIN memberships sm ON sm.id = mc.membership_id AND sm.stokvel_id = mc.stokvel_id
             LEFT JOIN users u ON u.id = sm.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE mc.stokvel_id = :stokvel_id
               AND mc.status IN (\'pending\', \'partial\', \'overdue\')
             ORDER BY mc.due_date ASC',
            ['stokvel_id' => $stokvelId]
        );
    }

    /** @param array<string, mixed> $filters */
    public function arrearsAgeing(int $stokvelId, array $filters = []): array
    {
        $asOf = (string) ($filters['as_of'] ?? date('Y-m-d'));

        return $this->db->fetchAll(
            'SELECT mc.membership_id, mc.charge_code, mc.due_date,
                    (mc.amount - mc.amount_paid) AS balance_due,
                    DATEDIFF(:as_of, mc.due_date) AS days_overdue,
                    CASE
                        WHEN DATEDIFF(:as_of, mc.due_date) <= 30 THEN \'0-30\'
                        WHEN DATEDIFF(:as_of, mc.due_date) <= 60 THEN \'31-60\'
                        WHEN DATEDIFF(:as_of, mc.due_date) <= 90 THEN \'61-90\'
                        ELSE \'90+\'
                    END AS ageing_bucket
             FROM member_charges mc
             WHERE mc.stokvel_id = :stokvel_id
               AND mc.status IN (\'pending\', \'partial\', \'overdue\')
               AND (mc.amount - mc.amount_paid) > 0
             ORDER BY days_overdue DESC',
            ['stokvel_id' => $stokvelId, 'as_of' => $asOf]
        );
    }

    /** @param array<string, mixed> $filters */
    public function transactionLedger(int $stokvelId, array $filters = []): array
    {
        [$from, $to] = $this->dateRange($filters);

        return $this->db->fetchAll(
            'SELECT je.entry_number, je.entry_date, je.description, je.status,
                    jl.line_number, jl.description AS line_description, jl.debit, jl.credit,
                    coa.code AS account_code, coa.name AS account_name
             FROM journal_lines jl
             INNER JOIN journal_entries je ON je.id = jl.journal_entry_id AND je.stokvel_id = jl.stokvel_id
             INNER JOIN chart_of_accounts coa ON coa.id = jl.chart_of_account_id AND coa.stokvel_id = jl.stokvel_id
             WHERE jl.stokvel_id = :stokvel_id AND je.entry_date BETWEEN :from_date AND :to_date
             ORDER BY je.entry_date, je.entry_number, jl.line_number',
            ['stokvel_id' => $stokvelId, 'from_date' => $from, 'to_date' => $to]
        );
    }

    /** @param array<string, mixed> $filters */
    public function trialBalance(int $stokvelId, array $filters = []): array
    {
        [$from, $to] = $this->dateRange($filters);

        return $this->db->fetchAll(
            'SELECT coa.code, coa.name, coa.account_type,
                    COALESCE(SUM(jl.debit), 0) AS total_debit,
                    COALESCE(SUM(jl.credit), 0) AS total_credit,
                    COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0) AS net_balance
             FROM chart_of_accounts coa
             LEFT JOIN journal_lines jl ON jl.chart_of_account_id = coa.id AND jl.stokvel_id = coa.stokvel_id
             LEFT JOIN journal_entries je ON je.id = jl.journal_entry_id
                AND je.stokvel_id = jl.stokvel_id
                AND je.status = :posted
                AND je.entry_date BETWEEN :from_date AND :to_date
             WHERE coa.stokvel_id = :stokvel_id AND coa.is_active = 1
             GROUP BY coa.id, coa.code, coa.name, coa.account_type
             ORDER BY coa.code',
            [
                'stokvel_id' => $stokvelId,
                'posted' => 'posted',
                'from_date' => $from,
                'to_date' => $to,
            ]
        );
    }

    /** @param array<string, mixed> $filters */
    public function incomeExpense(int $stokvelId, array $filters = []): array
    {
        [$from, $to] = $this->dateRange($filters);

        return $this->db->fetchAll(
            'SELECT coa.account_type, coa.code, coa.name,
                    COALESCE(SUM(jl.credit), 0) - COALESCE(SUM(jl.debit), 0) AS net_amount
             FROM chart_of_accounts coa
             INNER JOIN journal_lines jl ON jl.chart_of_account_id = coa.id AND jl.stokvel_id = coa.stokvel_id
             INNER JOIN journal_entries je ON je.id = jl.journal_entry_id AND je.stokvel_id = jl.stokvel_id
             WHERE coa.stokvel_id = :stokvel_id
               AND je.status = :posted
               AND je.entry_date BETWEEN :from_date AND :to_date
               AND coa.account_type IN (\'income\', \'expense\')
             GROUP BY coa.id, coa.account_type, coa.code, coa.name
             ORDER BY coa.account_type, coa.code',
            [
                'stokvel_id' => $stokvelId,
                'posted' => 'posted',
                'from_date' => $from,
                'to_date' => $to,
            ]
        );
    }

    /** @param array<string, mixed> $filters */
    public function cashMovement(int $stokvelId, array $filters = []): array
    {
        [$from, $to] = $this->dateRange($filters);

        if ($this->tableExists('contributions')) {
            return $this->db->fetchAll(
                'SELECT DATE(paid_at) AS movement_date, SUM(amount) AS total_inflow, COUNT(*) AS txn_count
                 FROM contributions
                 WHERE stokvel_id = :stokvel_id AND status = :status
                   AND paid_at BETWEEN :from_date AND :to_date
                 GROUP BY DATE(paid_at)
                 ORDER BY movement_date',
                [
                    'stokvel_id' => $stokvelId,
                    'status' => 'posted',
                    'from_date' => $from,
                    'to_date' => $to,
                ]
            );
        }

        return $this->db->fetchAll(
            'SELECT DATE(paid_at) AS movement_date, SUM(amount) AS total_inflow, COUNT(*) AS txn_count
             FROM payment_transactions
             WHERE stokvel_id = :stokvel_id AND status = :status
               AND paid_at BETWEEN :from_date AND :to_date
             GROUP BY DATE(paid_at)
             ORDER BY movement_date',
            [
                'stokvel_id' => $stokvelId,
                'status' => 'completed',
                'from_date' => $from . ' 00:00:00',
                'to_date' => $to . ' 23:59:59',
            ]
        );
    }

    /** @param array<string, mixed> $filters */
    public function expenseReport(int $stokvelId, array $filters = []): array
    {
        [$from, $to] = $this->dateRange($filters);
        $dateCol = $this->columnExists('expenses', 'expense_date') ? 'expense_date' : 'incurred_at';

        return $this->db->fetchAll(
            "SELECT e.id, e.expense_ref, e.description, e.amount, e.status, e.{$dateCol} AS expense_date,
                    ec.name AS category_name
             FROM expenses e
             INNER JOIN expense_categories ec ON ec.id = e.expense_category_id AND ec.stokvel_id = e.stokvel_id
             WHERE e.stokvel_id = :stokvel_id AND e.{$dateCol} BETWEEN :from_date AND :to_date
             ORDER BY e.{$dateCol} DESC",
            ['stokvel_id' => $stokvelId, 'from_date' => $from, 'to_date' => $to]
        );
    }

    /** @param array<string, mixed> $filters */
    public function loanPortfolio(int $stokvelId, array $filters = []): array
    {
        return $this->db->fetchAll(
            'SELECT l.loan_ref, l.principal_amount, l.interest_rate_percent, l.term_months,
                    l.status, l.outstanding_balance, l.disbursed_at, m.member_number,
                    p.first_name, p.last_name, u.email
             FROM loans l
             INNER JOIN memberships m ON m.id = l.membership_id AND m.stokvel_id = l.stokvel_id
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE l.stokvel_id = :stokvel_id
             ORDER BY l.created_at DESC',
            ['stokvel_id' => $stokvelId]
        );
    }

    /** @param array<string, mixed> $filters */
    public function loanArrears(int $stokvelId, array $filters = []): array
    {
        $asOf = (string) ($filters['as_of'] ?? date('Y-m-d'));

        return $this->db->fetchAll(
            'SELECT ls.loan_id, l.loan_ref, ls.instalment_number, ls.due_date, ls.total_due, ls.amount_paid,
                    (ls.total_due - ls.amount_paid) AS balance_due,
                    DATEDIFF(:as_of, ls.due_date) AS days_overdue
             FROM loan_schedules ls
             INNER JOIN loans l ON l.id = ls.loan_id AND l.stokvel_id = ls.stokvel_id
             WHERE ls.stokvel_id = :stokvel_id
               AND ls.status IN (\'pending\', \'partial\', \'overdue\')
               AND ls.due_date < :as_of
               AND (ls.total_due - ls.amount_paid) > 0
             ORDER BY days_overdue DESC',
            ['stokvel_id' => $stokvelId, 'as_of' => $asOf]
        );
    }

    /** @param array<string, mixed> $filters */
    public function payoutSchedule(int $stokvelId, array $filters = []): array
    {
        return $this->db->fetchAll(
            'SELECT ps.id, ps.sequence_number, ps.scheduled_date, ps.payout_amount, ps.status, ps.paid_at,
                    pc.name AS cycle_name, m.member_number, p.first_name, p.last_name
             FROM payout_schedule ps
             INNER JOIN payout_cycles pc ON pc.id = ps.payout_cycle_id AND pc.stokvel_id = ps.stokvel_id
             INNER JOIN memberships m ON m.id = ps.membership_id AND m.stokvel_id = ps.stokvel_id
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE ps.stokvel_id = :stokvel_id
             ORDER BY ps.scheduled_date ASC',
            ['stokvel_id' => $stokvelId]
        );
    }

    /** @param array<string, mixed> $filters */
    public function distributionReport(int $stokvelId, array $filters = []): array
    {
        [$from, $to] = $this->dateRange($filters);

        return $this->db->fetchAll(
            'SELECT d.distribution_ref, d.amount, d.status, d.paid_at, d.payment_reference,
                    ps.scheduled_date, m.member_number, p.first_name, p.last_name
             FROM distributions d
             INNER JOIN payout_schedule ps ON ps.id = d.payout_schedule_id AND ps.stokvel_id = d.stokvel_id
             INNER JOIN memberships m ON m.id = ps.membership_id AND m.stokvel_id = d.stokvel_id
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE d.stokvel_id = :stokvel_id
               AND ps.scheduled_date BETWEEN :from_date AND :to_date
             ORDER BY ps.scheduled_date, d.id',
            ['stokvel_id' => $stokvelId, 'from_date' => $from, 'to_date' => $to]
        );
    }

    /** @param array<string, mixed> $filters */
    public function meetingAttendance(int $stokvelId, array $filters = []): array
    {
        [$from, $to] = $this->dateRange($filters);

        return $this->db->fetchAll(
            'SELECT m.title, m.scheduled_at, m.status, ma.attendance_status,
                    p.first_name, p.last_name, u.email, ms.member_number
             FROM meetings m
             LEFT JOIN meeting_attendance ma ON ma.meeting_id = m.id AND ma.stokvel_id = m.stokvel_id
             LEFT JOIN memberships ms ON ms.id = ma.membership_id AND ms.stokvel_id = m.stokvel_id
             LEFT JOIN users u ON u.id = ms.user_id
             LEFT JOIN user_profiles p ON p.user_id = u.id
             WHERE m.stokvel_id = :stokvel_id
               AND m.scheduled_at BETWEEN :from_dt AND :to_dt
             ORDER BY m.scheduled_at DESC, p.last_name',
            [
                'stokvel_id' => $stokvelId,
                'from_dt' => $from . ' 00:00:00',
                'to_dt' => $to . ' 23:59:59',
            ]
        );
    }

    /** @param array<string, mixed> $filters */
    public function penaltyReport(int $stokvelId, array $filters = []): array
    {
        [$from, $to] = $this->dateRange($filters);

        if ($this->columnExists('member_charges', 'penalty_amount')) {
            return $this->db->fetchAll(
                'SELECT mc.id, mc.membership_id, mc.charge_code, mc.due_date, mc.penalty_amount, mc.status
                 FROM member_charges mc
                 WHERE mc.stokvel_id = :stokvel_id
                   AND mc.penalty_amount > 0
                   AND mc.due_date BETWEEN :from_date AND :to_date
                 ORDER BY mc.due_date DESC',
                ['stokvel_id' => $stokvelId, 'from_date' => $from, 'to_date' => $to]
            );
        }

        return $this->db->fetchAll(
            'SELECT mc.id, mc.membership_id, mc.charge_code, mc.due_date, mc.status,
                    (mc.amount - mc.amount_paid) AS outstanding
             FROM member_charges mc
             WHERE mc.stokvel_id = :stokvel_id AND mc.status = :overdue
             ORDER BY mc.due_date DESC',
            ['stokvel_id' => $stokvelId, 'overdue' => 'overdue']
        );
    }

    /** @param array<string, mixed> $filters */
    public function auditReport(int $stokvelId, array $filters = []): array
    {
        [$from, $to] = $this->dateRange($filters);

        return $this->db->fetchAll(
            'SELECT al.created_at, al.action, al.entity_type, al.entity_id, al.user_id,
                    u.email AS user_email, al.ip_address
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.stokvel_id = :stokvel_id
               AND al.created_at BETWEEN :from_dt AND :to_dt
             ORDER BY al.created_at DESC
             LIMIT 5000',
            [
                'stokvel_id' => $stokvelId,
                'from_dt' => $from . ' 00:00:00',
                'to_dt' => $to . ' 23:59:59',
            ]
        );
    }

    /** @param list<array<string, mixed>> $rows */
    public function exportCsv(array $rows, ?array $headers = null): string
    {
        if ($rows === []) {
            return '';
        }

        $headers ??= array_keys($rows[0]);
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }

        fputcsv($out, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? '';
            }
            fputcsv($out, $line);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv === false ? '' : $csv;
    }

    /** @param array<string, mixed> $filters
     * @return array{0: string, 1: string}
     */
    private function dateRange(array $filters): array
    {
        $from = (string) ($filters['from'] ?? date('Y-m-01'));
        $to = (string) ($filters['to'] ?? date('Y-m-d'));

        return [$from, $to];
    }

    private function tableExists(string $table): bool
    {
        $row = $this->db->fetch(
            'SELECT 1 AS ok FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1',
            ['table' => $table]
        );

        return $row !== null;
    }

    private function columnExists(string $table, string $column): bool
    {
        $row = $this->db->fetch(
            'SELECT 1 AS ok FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1',
            ['table' => $table, 'column' => $column]
        );

        return $row !== null;
    }
}
