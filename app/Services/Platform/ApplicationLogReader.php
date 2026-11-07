<?php

declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Streams and filters rotated application JSON log files without loading entire files.
 */
final class ApplicationLogReader
{
    public function __construct(private readonly string $logDir)
    {
    }

    /**
     * @param array{q?:string,level?:string,date_from?:string,date_to?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $q = strtolower(trim((string) ($filters['q'] ?? '')));
        $level = strtoupper(trim((string) ($filters['level'] ?? '')));
        $from = $this->parseDate((string) ($filters['date_from'] ?? ''));
        $to = $this->parseDate((string) ($filters['date_to'] ?? ''), true);

        $files = $this->listLogFiles($from, $to);
        $matched = [];
        foreach ($files as $file) {
            $this->scanFile($file, $q, $level, $from, $to, $matched, 5000);
            if (count($matched) >= 5000) {
                break;
            }
        }

        // Newest first.
        usort($matched, static fn (array $a, array $b): int => strcmp((string) ($b['ts'] ?? ''), (string) ($a['ts'] ?? '')));
        $total = count($matched);
        $slice = array_slice($matched, ($page - 1) * $perPage, $perPage);

        return [
            'items' => $slice,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param list<array<string,mixed>> $matched
     */
    private function scanFile(
        string $file,
        string $q,
        string $level,
        ?string $from,
        ?string $to,
        array &$matched,
        int $limit
    ): void {
        $fh = @fopen($file, 'rb');
        if ($fh === false) {
            return;
        }
        while (($line = fgets($fh)) !== false) {
            if (count($matched) >= $limit) {
                break;
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $entry = json_decode($line, true);
            if (!is_array($entry)) {
                // Non-JSON lines (e.g. cron.log) — wrap safely.
                $entry = [
                    'ts' => date('Y-m-d H:i:s', (int) @filemtime($file)),
                    'level' => 'INFO',
                    'message' => $this->redactString(mb_substr($line, 0, 500)),
                    'context' => ['source_file' => basename($file)],
                ];
            }
            $entryLevel = strtoupper((string) ($entry['level'] ?? 'INFO'));
            $ts = (string) ($entry['ts'] ?? '');
            if ($level !== '' && $entryLevel !== $level) {
                continue;
            }
            if ($from !== null && $ts !== '' && $ts < $from) {
                continue;
            }
            if ($to !== null && $ts !== '' && $ts > $to) {
                continue;
            }
            $message = $this->redactString((string) ($entry['message'] ?? ''));
            $context = is_array($entry['context'] ?? null) ? $this->redactContext($entry['context']) : [];
            if ($q !== '') {
                $hay = strtolower($message . ' ' . json_encode($context));
                if (!str_contains($hay, $q)) {
                    continue;
                }
            }
            $matched[] = [
                'ts' => $ts,
                'level' => $entryLevel,
                'message' => $message,
                'request_id' => (string) ($entry['request_id'] ?? ''),
                'context' => $context,
                'source' => 'application',
                'source_file' => basename($file),
            ];
        }
        fclose($fh);
    }

    /** @return list<string> */
    private function listLogFiles(?string $from, ?string $to): array
    {
        if (!is_dir($this->logDir)) {
            return [];
        }
        $files = glob($this->logDir . '/app-*.log') ?: [];
        $files = array_merge($files, glob($this->logDir . '/cron.log') ?: []);
        rsort($files, SORT_STRING);
        $out = [];
        foreach ($files as $file) {
            $base = basename($file);
            if (preg_match('/^app-(\d{4}-\d{2}-\d{2})\.log$/', $base, $m)) {
                $day = $m[1];
                if ($from !== null && $day . ' 00:00:00' < substr($from, 0, 10) . ' 00:00:00' && $day < substr($from, 0, 10)) {
                    continue;
                }
                if ($to !== null && $day > substr($to, 0, 10)) {
                    continue;
                }
            }
            $out[] = $file;
        }
        return $out;
    }

    private function parseDate(string $value, bool $endOfDay = false): ?string
    {
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        return $value . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
    }

    /** @param array<string, mixed> $context */
    private function redactContext(array $context): array
    {
        $sensitive = ['password', 'token', 'secret', 'api_key', 'authorization', 'id_number', 'bank_account', 'card_number'];
        $out = [];
        foreach ($context as $key => $value) {
            $lk = strtolower((string) $key);
            foreach ($sensitive as $needle) {
                if (str_contains($lk, $needle)) {
                    $out[$key] = '[REDACTED]';
                    continue 2;
                }
            }
            if (is_array($value)) {
                $out[$key] = $this->redactContext($value);
            } else {
                $out[$key] = $this->redactString((string) $value);
            }
        }
        return $out;
    }

    private function redactString(string $value): string
    {
        $value = preg_replace('/\b\d{13}\b/', '[ID_REDACTED]', $value) ?? $value;
        $value = preg_replace('/(Bearer\s+)[A-Za-z0-9\-._~+\/]+=*/i', '$1[REDACTED]', $value) ?? $value;
        return mb_substr($value, 0, 2000);
    }
}
