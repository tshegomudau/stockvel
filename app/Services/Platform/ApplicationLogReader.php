<?php

declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Streams and filters rotated application / deploy / cron log files without loading entire files.
 */
final class ApplicationLogReader
{
    public function __construct(private readonly string $logDir)
    {
    }

    /**
     * @param array{q?:string,level?:string,date_from?:string,date_to?:string,kind?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int}
     */
    public function search(array $filters, int $page = 1, int $perPage = 50): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $q = strtolower(trim((string) ($filters['q'] ?? '')));
        $level = strtoupper(trim((string) ($filters['level'] ?? '')));
        $kind = strtolower(trim((string) ($filters['kind'] ?? '')));
        $from = $this->parseDate((string) ($filters['date_from'] ?? ''));
        $to = $this->parseDate((string) ($filters['date_to'] ?? ''), true);

        $files = $this->listLogFiles($from, $to, $kind);
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
        $base = basename($file);
        $fileDay = $this->dayFromFilename($base);
        $fallbackTs = $fileDay !== null
            ? $fileDay . ' 12:00:00'
            : date('Y-m-d H:i:s', (int) @filemtime($file));
        $kind = $this->kindFromFilename($base);

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
                $parsed = $this->parsePlainLine($line, $fallbackTs, $kind);
                $entry = $parsed;
            }
            $entryLevel = strtoupper((string) ($entry['level'] ?? 'INFO'));
            $ts = (string) ($entry['ts'] ?? $fallbackTs);
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
                $hay = strtolower($message . ' ' . json_encode($context) . ' ' . $base);
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
                'source' => $kind,
                'source_file' => $base,
            ];
        }
        fclose($fh);
    }

    /** @return array{ts:string,level:string,message:string,context:array<string,mixed>} */
    private function parsePlainLine(string $line, string $fallbackTs, string $kind): array
    {
        $ts = $fallbackTs;
        $message = $line;
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\s+[A-Za-z0-9_+\-]+)?\]\s*(.*)$/s', $line, $m)) {
            $ts = $m[1];
            $message = trim((string) $m[2]);
        }

        $upper = strtoupper($message);
        $level = 'INFO';
        if (str_contains($upper, 'ERROR') || str_contains($upper, 'FATAL')) {
            $level = 'ERROR';
        } elseif (str_contains($upper, 'WARN')) {
            $level = 'WARNING';
        } elseif (str_contains($upper, 'SECURITY')) {
            $level = 'SECURITY';
        }

        return [
            'ts' => $ts,
            'level' => $level,
            'message' => $this->redactString(mb_substr($message !== '' ? $message : $line, 0, 500)),
            'context' => ['kind' => $kind],
        ];
    }

    /** @return list<string> */
    private function listLogFiles(?string $from, ?string $to, string $kind = ''): array
    {
        if (!is_dir($this->logDir)) {
            return [];
        }

        $files = [];
        if ($kind === '' || $kind === 'application' || $kind === 'app') {
            $files = array_merge($files, glob($this->logDir . '/app-*.log') ?: []);
        }
        if ($kind === '' || $kind === 'deploy') {
            $files = array_merge($files, glob($this->logDir . '/deploy-*.log') ?: []);
        }
        if ($kind === '' || $kind === 'cron') {
            $files = array_merge($files, glob($this->logDir . '/cron.log') ?: []);
        }

        rsort($files, SORT_STRING);
        $out = [];
        foreach ($files as $file) {
            $base = basename($file);
            $day = $this->dayFromFilename($base);
            if ($day !== null) {
                if ($from !== null && $day < substr($from, 0, 10)) {
                    continue;
                }
                if ($to !== null && $day > substr($to, 0, 10)) {
                    continue;
                }
            } elseif ($from !== null || $to !== null) {
                // Undated files (e.g. cron.log): keep and rely on per-line timestamps.
            }
            $out[] = $file;
        }
        return $out;
    }

    private function dayFromFilename(string $base): ?string
    {
        if (preg_match('/^app-(\d{4}-\d{2}-\d{2})\.log$/', $base, $m)) {
            return $m[1];
        }
        if (preg_match('/^deploy-(\d{4})(\d{2})(\d{2})\.log$/', $base, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        return null;
    }

    private function kindFromFilename(string $base): string
    {
        if (str_starts_with($base, 'deploy-')) {
            return 'deploy';
        }
        if ($base === 'cron.log' || str_starts_with($base, 'cron')) {
            return 'cron';
        }

        return 'application';
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
