<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Platform\PlatformSettingsService;

/**
 * Restrict rich-text HTML to a safe subset of tags and attributes.
 * Extra tags (img, table, style colours) follow platform feature flags.
 */
final class HtmlSanitizer
{
    private const BASE_TAGS = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><h4><a><blockquote><hr><div><span><sub><sup><pre><code>';

    /** @return array{images:bool,tables:bool,colors:bool,full:bool} */
    public static function featureOptions(): array
    {
        try {
            /** @var PlatformSettingsService $settings */
            $settings = app()->container()->get(PlatformSettingsService::class);
            $full = $settings->enabled('wysiwyg_full_enabled');

            return [
                'full' => $full,
                'images' => $full && $settings->enabled('wysiwyg_images_enabled'),
                'tables' => $full && $settings->enabled('wysiwyg_tables_enabled'),
                'colors' => $full && $settings->enabled('wysiwyg_colors_enabled'),
            ];
        } catch (\Throwable) {
            return ['full' => false, 'images' => false, 'tables' => false, 'colors' => false];
        }
    }

    public static function clean(?string $html, ?array $options = null): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $opts = $options ?? self::featureOptions();
        $allowed = self::BASE_TAGS;
        if (!empty($opts['images'])) {
            $allowed .= '<img>';
        }
        if (!empty($opts['tables'])) {
            $allowed .= '<table><thead><tbody><tfoot><tr><th><td><caption>';
        }

        // Always strip executable / embedded content first.
        $html = preg_replace(
            '#<\s*(script|style|iframe|object|embed|link|meta|form|svg|math|video|audio|source|picture)\b[^>]*>.*?<\s*/\s*\1\s*>#is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '#<\s*(script|style|iframe|object|embed|link|meta|form|svg|math|video|audio|source|picture)\b[^>]*/?\s*>#is',
            '',
            $html
        ) ?? $html;

        if (empty($opts['images'])) {
            $html = preg_replace('#<\s*img\b[^>]*/?\s*>#is', '', $html) ?? $html;
        }

        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\s+on\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $clean) ?? $clean;

        if (empty($opts['colors'])) {
            $clean = preg_replace('/\s+style\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $clean) ?? $clean;
        } else {
            $clean = preg_replace_callback(
                '/\s+style\s*=\s*("([^"]*)"|\'([^\']*)\')/iu',
                static function (array $m): string {
                    $raw = $m[2] ?? $m[3] ?? '';
                    $safe = [];
                    if (preg_match('/color\s*:\s*([^;]+)/i', $raw, $cm)) {
                        $c = trim($cm[1]);
                        if (preg_match('/^(#[0-9a-f]{3,8}|rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)|[a-z]+)$/i', $c)) {
                            $safe[] = 'color:' . $c;
                        }
                    }
                    if (preg_match('/background-color\s*:\s*([^;]+)/i', $raw, $bm)) {
                        $c = trim($bm[1]);
                        if (preg_match('/^(#[0-9a-f]{3,8}|rgb\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*\)|[a-z]+)$/i', $c)) {
                            $safe[] = 'background-color:' . $c;
                        }
                    }
                    if ($safe === []) {
                        return '';
                    }

                    return ' style="' . htmlspecialchars(implode(';', $safe), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                },
                $clean
            ) ?? $clean;
        }

        $clean = preg_replace_callback(
            '/\s(href|src|xlink:href)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/iu',
            static function (array $m) use ($opts): string {
                $attr = strtolower($m[1]);
                $raw = $m[2] ?? '';
                if ($raw === '' && isset($m[3])) {
                    $raw = $m[3];
                }
                if ($raw === '' && isset($m[4])) {
                    $raw = $m[4];
                }
                $value = html_entity_decode(trim((string) $raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if ($value === '') {
                    return '';
                }

                $lower = strtolower(preg_replace('/\s+/', '', $value) ?? $value);
                if (
                    str_starts_with($lower, 'javascript:')
                    || str_starts_with($lower, 'vbscript:')
                ) {
                    return '';
                }

                if ($attr === 'src') {
                    if (empty($opts['images'])) {
                        return '';
                    }
                    // Allow https images and same-origin media proxy paths.
                    if (
                        preg_match('/^https?:\/\//i', $value)
                        || preg_match('#^(/app/media/\d+|https?://)#i', $value)
                    ) {
                        return ' src="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                    }

                    return '';
                }

                if (!preg_match('/^(https?:|mailto:|\/|#|\.\/)/i', $value)) {
                    return '';
                }

                return ' href="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            },
            $clean
        ) ?? $clean;

        // Keep safe alignment / table attributes when full editor is on.
        if (!empty($opts['full']) || !empty($opts['tables'])) {
            $clean = preg_replace_callback(
                '/\s(align|colspan|rowspan|width|height|alt)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/iu',
                static function (array $m): string {
                    $attr = strtolower($m[1]);
                    $raw = $m[2] ?? $m[3] ?? $m[4] ?? '';
                    $raw = trim((string) $raw);
                    if ($raw === '' || preg_match('/[<>"\']/u', $raw)) {
                        return '';
                    }
                    if (in_array($attr, ['colspan', 'rowspan', 'width', 'height'], true) && !ctype_digit($raw)) {
                        return '';
                    }
                    if ($attr === 'align' && !in_array(strtolower($raw), ['left', 'right', 'center', 'justify'], true)) {
                        return '';
                    }

                    return ' ' . $attr . '="' . htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                },
                $clean
            ) ?? $clean;
        }

        return trim($clean);
    }
}
