<?php
declare(strict_types=1);

namespace App\Core;

/** Render template PHP biasa di dalam layout. */
final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/public'): void
    {
        $content = self::capture($view, $data);
        echo self::capture($layout, $data + ['content' => $content]);
        unset($_SESSION['_old']); // input lama hanya hidup satu request
    }

    /** Render tanpa layout (untuk potongan HTML hasil Ajax). */
    public static function partial(string $view, array $data = []): void
    {
        echo self::capture($view, $data);
    }

    private static function capture(string $view, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require APP_PATH . '/views/' . $view . '.php';
        return (string) ob_get_clean();
    }
}
