<?php

declare(strict_types=1);

use App\Support\HtmlSanitizer;
use Tests\TestRunner;

return static function (TestRunner $runner): void {
    $runner->add('HtmlSanitizer strips script tags and event handlers', function (): void {
        $dirty = '<p onclick="alert(1)">Hello</p><script>alert(2)</script><a href="javascript:alert(3)">x</a>';
        $clean = HtmlSanitizer::clean($dirty);
        test_assert(!str_contains($clean, '<script'), 'script tag must be removed');
        test_assert(!str_contains(strtolower($clean), 'onclick'), 'onclick must be removed');
        test_assert(!str_contains(strtolower($clean), 'javascript:'), 'javascript: URL must be removed');
        test_assert(str_contains($clean, 'Hello'), 'safe text must remain');
    }, 'security');

    $runner->add('HtmlSanitizer keeps safe formatting tags', function (): void {
        $html = '<p><strong>Bold</strong> and <em>italic</em></p><ul><li>One</li></ul>';
        $clean = HtmlSanitizer::clean($html);
        test_assert(str_contains($clean, '<strong>'), 'strong kept');
        test_assert(str_contains($clean, '<em>'), 'em kept');
        test_assert(str_contains($clean, '<li>'), 'list kept');
    }, 'security');
};
