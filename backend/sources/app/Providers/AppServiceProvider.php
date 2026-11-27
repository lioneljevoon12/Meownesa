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
        // Kunci pembatas laju per email + IP. Email yang bukan teks (mis. email[]=x) dianggap kosong agar permintaan
        // salah bentuk tetap sampai ke validasi (422) alih-alih membuat pembatas melempar galat (500).
        $kunciEmailIp = fn (Request $request) => Str::lower(is_string($request->input('email')) ? $request->input('email') : '').'|'.$request->ip();

        // Batasi percobaan login: 5x/menit per kombinasi email + IP (cegah brute force).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($kunciEmailIp($request)));

        // Formulir adopsi publik (tanpa akun): batasi per IP agar tidak dibanjiri pengajuan palsu.
        // Cukup longgar untuk jaringan kampus yang berbagi satu IP; duplikat per pelamar dicegah di aplikasi.
        RateLimiter::for('adopsi-tulis', fn (Request $request) => Limit::perHour(30)->by($request->ip()));

        // Ganti password sendiri: butuh password lama, jadi batasi tebakan per akun.
        RateLimiter::for('ubah-password', fn (Request $request) => Limit::perMinute(5)->by((string) ($request->user()?->id_user ?? $request->ip())));

        // Minta tautan reset password (publik): per email+IP dan per IP, agar tidak dipakai membanjiri kotak masuk orang lain.
        RateLimiter::for('lupa-password', fn (Request $request) => [
            Limit::perMinute(3)->by($kunciEmailIp($request)),
            Limit::perHour(10)->by($request->ip()),
        ]);

        // Perpanjang token (HP lapangan): longgar karena dipanggil berkala, tetapi tetap dibatasi per akun.
        RateLimiter::for('perpanjang', fn (Request $request) => Limit::perMinute(20)->by((string) ($request->user()?->id_user ?? $request->ip())));

        // Pelacakan status: kode berentropi tinggi, namun tetap dibatasi agar tidak bisa ditebak massal.
        RateLimiter::for('adopsi-tracking', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
