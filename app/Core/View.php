<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    private static string $basePath;

    public static function setBasePath(string $path): void
    {
        self::$basePath = $path;
    }

    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::renderPartial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::renderPartial($layout, array_merge($data, ['content' => $content]));
    }

    public static function renderPartial(string $template, array $data = []): string
    {
        $path = self::$basePath . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($path)) {
            throw new RuntimeException("View not found: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}
