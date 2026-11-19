<?php
declare(strict_types=1);

namespace App\Core;

/** Middleware siap pakai. */
final class Guard
{
    public static function auth(): callable
    {
        return static function (): void {
            if (!Auth::check()) {
                flash('error', 'Silakan login terlebih dahulu.');
                redirect('login');
            }
        };
    }

    public static function role(string ...$roles): callable
    {
        return static function () use ($roles): void {
            if (!Auth::check()) {
                flash('error', 'Silakan login terlebih dahulu.');
                redirect('login');
            }
            if (!Auth::hasRole(...$roles)) {
                http_response_code(403);
                View::render('error', ['code' => 403, 'message' => 'Anda tidak memiliki hak akses ke halaman ini.']);
                exit;
            }
        };
    }
}
