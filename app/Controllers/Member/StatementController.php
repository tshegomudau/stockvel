<?php

declare(strict_types=1);

namespace App\Controllers\Member;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;

final class StatementController extends Controller
{
    public function show(Request $request): Response
    {
        $this->requireAuth();
        $sid = $this->requireStokvel();
        $userId = (int) $this->userId();

        $targetMembershipId = (int) $request->query('membership_id', 0);
        $membership = $this->resolveMembership($sid, $userId, $targetMembershipId);

        $period = $this->resolvePeriod($request, $sid);
        $from = $period['from'];
        $to = $period['to'];
        $membershipId = (int) $membership['id'];

        $charges = $this->db()->fetchAll(
            "SELECT due_date AS entry_date, description, amount, 'debit' AS side,
                    charge_code AS reference, 'charge' AS entry_type
             FROM member_charges
             WHERE stokvel_id = :sid AND membership_id = :mid
               AND due_date >= :from AND due_date <= :to
             ORDER BY due_date ASC, id ASC",
            ['sid' => $sid, 'mid' => $membershipId, 'from' => $from, 'to' => $to]
        );
        $payments = $this->db()->fetchAll(
            "SELECT paid_at AS entry_date,
                    CONCAT('Payment recorded', IF(payment_method IS NULL OR payment_method = '', '', CONCAT(' (', payment_method, ')'))) AS description,
                    amount, 'credit' AS side,
                    COALESCE(transaction_ref, CAST(id AS CHAR)) AS reference, 'payment' AS entry_type
             FROM payment_transactions
             WHERE stokvel_id = :sid AND membership_id = :mid
               AND status IN ('posted','completed','allocated','confirmed')
               AND DATE(paid_at) >= :from AND DATE(paid_at) <= :to
             ORDER BY paid_at ASC, id ASC",
            ['sid' => $sid, 'mid' => $membershipId, 'from' => $from, 'to' => $to]
        );
        $fines = $this->db()->fetchAll(
            "SELECT issued_at AS entry_date,
                    CONCAT('Fine: ', COALESCE(reason, 'Member fine')) AS description,
                    amount, 'debit' AS side,
                    CAST(id AS CHAR) AS reference, 'fine' AS entry_type
             FROM fines
             WHERE stokvel_id = :sid AND membership_id = :mid
               AND DATE(issued_at) >= :from AND DATE(issued_at) <= :to
               AND status NOT IN ('cancelled')
             ORDER BY issued_at ASC, id ASC",
            ['sid' => $sid, 'mid' => $membershipId, 'from' => $from, 'to' => $to]
        );

        $openingDebit = (float) ($this->db()->fetch(
            "SELECT COALESCE(SUM(amount),0) AS t FROM member_charges
             WHERE stokvel_id = :sid AND membership_id = :mid AND due_date < :from",
            ['sid' => $sid, 'mid' => $membershipId, 'from' => $from]
        )['t'] ?? 0);
        $openingCredit = (float) ($this->db()->fetch(
            "SELECT COALESCE(SUM(amount),0) AS t FROM payment_transactions
             WHERE stokvel_id = :sid AND membership_id = :mid
               AND status IN ('posted','completed','allocated','confirmed')
               AND DATE(paid_at) < :from",
            ['sid' => $sid, 'mid' => $membershipId, 'from' => $from]
        )['t'] ?? 0);
        $openingFine = (float) ($this->db()->fetch(
            "SELECT COALESCE(SUM(amount - amount_paid - COALESCE(waived_amount,0)),0) AS t FROM fines
             WHERE stokvel_id = :sid AND membership_id = :mid
               AND DATE(issued_at) < :from AND status NOT IN ('cancelled','waived','paid')",
            ['sid' => $sid, 'mid' => $membershipId, 'from' => $from]
        )['t'] ?? 0);

        // Positive opening = member owes stokvel (debits exceed credits)
        $openingBalance = round($openingDebit + $openingFine - $openingCredit, 2);

        $lines = array_merge($charges, $payments, $fines);
        usort($lines, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['entry_date'] ?? ''), (string) ($b['entry_date'] ?? ''));
            return $cmp !== 0 ? $cmp : strcmp((string) ($a['reference'] ?? ''), (string) ($b['reference'] ?? ''));
        });

        $running = $openingBalance;
        $enriched = [];
        foreach ($lines as $line) {
            $amount = round((float) ($line['amount'] ?? 0), 2);
            $debit = ($line['side'] ?? '') === 'debit' ? $amount : 0.0;
            $credit = ($line['side'] ?? '') === 'credit' ? $amount : 0.0;
            $running = round($running + $debit - $credit, 2);
            $enriched[] = [
                'entry_date' => $line['entry_date'] ?? '',
                'reference' => $line['reference'] ?? '',
                'description' => $line['description'] ?? '',
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $running,
                'entry_type' => $line['entry_type'] ?? '',
            ];
        }

        $stokvel = $this->db()->fetch('SELECT id, name FROM stokvels WHERE id = :id LIMIT 1', ['id' => $sid]) ?? [];
        $profile = $this->db()->fetch(
            'SELECT first_name, last_name FROM user_profiles WHERE user_id = :uid LIMIT 1',
            ['uid' => (int) $membership['user_id']]
        ) ?? [];

        if ($request->query('format') === 'csv') {
            return $this->csvResponse($stokvel, $profile, $membership, $from, $to, $openingBalance, $enriched);
        }

        $layout = $request->query('print') ? 'layouts/print' : 'layouts/app';

        return $this->render('member/statement', [
            'title' => 'My statement',
            'activeNav' => 'member-statement',
            'navContext' => 'member',
            'membership' => $membership,
            'stokvel' => $stokvel,
            'memberName' => trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? '')),
            'periodFrom' => $from,
            'periodTo' => $to,
            'periodKey' => $period['key'],
            'openingBalance' => $openingBalance,
            'closingBalance' => $running,
            'lines' => $enriched,
            'canViewOthers' => $this->tenant()->can('members.view') || $this->tenant()->can('reports.view'),
        ], $layout);
    }

    /**
     * @return array{from:string,to:string,key:string}
     */
    private function resolvePeriod(Request $request, int $stokvelId): array
    {
        $key = trim((string) $request->query('period', '90'));
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Africa/Johannesburg'));

        if ($key === 'custom') {
            $from = trim((string) $request->query('from', ''));
            $to = trim((string) $request->query('to', ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                throw new HttpException(422, 'Custom statement requires valid from and to dates (YYYY-MM-DD).');
            }
            if ($from > $to) {
                throw new HttpException(422, 'Statement from date must be on or before to date.');
            }
            return ['from' => $from, 'to' => $to, 'key' => 'custom'];
        }

        if ($key === 'fy') {
            $startMonth = $this->financialYearStartMonth($stokvelId);
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
            return ['from' => $from, 'to' => $to, 'key' => 'fy'];
        }

        $days = match ($key) {
            '30' => 30,
            '60' => 60,
            '90' => 90,
            default => 90,
        };
        $key = (string) $days;
        $from = $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
        $to = $today->format('Y-m-d');

        return ['from' => $from, 'to' => $to, 'key' => $key];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMembership(int $stokvelId, int $userId, int $targetMembershipId): array
    {
        if ($targetMembershipId > 0 && ($this->tenant()->can('members.view') || $this->tenant()->can('reports.view'))) {
            $membership = $this->db()->fetch(
                'SELECT * FROM memberships WHERE stokvel_id = :sid AND id = :mid LIMIT 1',
                ['sid' => $stokvelId, 'mid' => $targetMembershipId]
            );
            if (!$membership) {
                throw new HttpException(404, 'Membership not found.');
            }
            return $membership;
        }

        $membership = $this->db()->fetch(
            'SELECT * FROM memberships WHERE stokvel_id = :sid AND user_id = :uid LIMIT 1',
            ['sid' => $stokvelId, 'uid' => $userId]
        );
        if (!$membership) {
            throw new HttpException(404, 'Membership not found.');
        }
        return $membership;
    }

    /**
     * @param array<string, mixed> $stokvel
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $membership
     * @param list<array<string, mixed>> $lines
     */
    private function csvResponse(
        array $stokvel,
        array $profile,
        array $membership,
        string $from,
        string $to,
        float $openingBalance,
        array $lines
    ): Response {
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            throw new HttpException(500, 'Unable to generate CSV.');
        }
        fputcsv($fh, ['Stokvel', $stokvel['name'] ?? '']);
        fputcsv($fh, ['Member', trim(($profile['first_name'] ?? '') . ' ' . ($profile['last_name'] ?? ''))]);
        fputcsv($fh, ['Member number', $membership['member_number'] ?? $membership['id'] ?? '']);
        fputcsv($fh, ['Period from', $from]);
        fputcsv($fh, ['Period to', $to]);
        fputcsv($fh, ['Opening balance (amount owing)', number_format($openingBalance, 2, '.', '')]);
        fputcsv($fh, []);
        fputcsv($fh, ['Date', 'Reference', 'Description', 'Debit', 'Credit', 'Running balance']);
        foreach ($lines as $line) {
            fputcsv($fh, [
                $line['entry_date'],
                $line['reference'],
                $line['description'],
                $line['debit'] > 0 ? number_format((float) $line['debit'], 2, '.', '') : '',
                $line['credit'] > 0 ? number_format((float) $line['credit'], 2, '.', '') : '',
                number_format((float) $line['balance'], 2, '.', ''),
            ]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        $filename = 'member-statement-' . $from . '-to-' . $to . '.csv';
        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

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
