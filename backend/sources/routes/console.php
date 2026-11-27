<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// dev-backend-11: butuh cron `* * * * * php artisan schedule:run` di server.
// Pengingat dibuat tiap pagi (WIB); email PENDING (konfirmasi, status, pengingat) dikirim tiap 5 menit.
Schedule::command('notifikasi:ingatkan')->dailyAt('08:00')->timezone('Asia/Jakarta')->withoutOverlapping();
Schedule::command('notifikasi:kirim')->everyFiveMinutes()->withoutOverlapping();
