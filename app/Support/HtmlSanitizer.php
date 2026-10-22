<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Restrict rich-text HTML to a safe subset of tags and attributes.
 * Strips scripts, event handlers, and dangerous URL schemes.
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><h4><a><blockquote><hr><div><span>';

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $html = preg_replace(
            '#<\s*(script|style|iframe|object|embed|link|meta|form|svg|math|img|video|audio|source|picture)\b[^>]*>.*?<\s*/\s*\1\s*>#is',
            '',
            $html
        ) ?? $html;
        $html = preg_replace(
            '#<\s*(script|style|iframe|object|embed|link|meta|form|svg|math|img|video|audio|source|picture)\b[^>]*/?\s*>#is',
            '',
            $html
        ) ?? $html;

        $clean = strip_tags($html, self::ALLOWED_TAGS);
        $clean = preg_replace('/\s+on\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+style\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $clean) ?? $clean;

        $clean = preg_replace_callback(
            '/\s(href|src|xlink:href)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/iu',
            static function (array $m): string {
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
                    || str_starts_with($lower, 'data:')
                ) {
                    return '';
                }

                if ($attr !== 'href') {
                    return '';
                }

                if (!preg_match('/^(https?:|mailto:|\/|#|\.\/)/i', $value)) {
                    return '';
                }

                return ' href="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            },
            $clean
        ) ?? $clean;

        return trim($clean);
    }
}
