<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Batasi percobaan login: 5x/menit per kombinasi email + IP (cegah brute force).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        // Formulir adopsi publik (tanpa akun): batasi per IP agar tidak dibanjiri pengajuan palsu.
        // Cukup longgar untuk jaringan kampus yang berbagi satu IP; duplikat per pelamar dicegah di aplikasi.
        RateLimiter::for('adopsi-tulis', fn (Request $request) => Limit::perHour(30)->by($request->ip()));

        // Pelacakan status: kode berentropi tinggi, namun tetap dibatasi agar tidak bisa ditebak massal.
        RateLimiter::for('adopsi-tracking', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
