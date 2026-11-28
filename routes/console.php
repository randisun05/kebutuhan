<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sinkronisasi pegawai dari SIASN setiap malam (aktifkan dengan SIASN_SYNC_TERJADWAL=true)
Schedule::command('siasn:sync pegawai')->dailyAt('01:00')->withoutOverlapping()
    ->when(fn () => config('siasn.enabled') && config('siasn.scheduled'));

// Rekam jejak bulanan kebutuhan vs existing (baris bulan berjalan diperbarui setiap hari)
Schedule::command('kebutuhan:snapshot')->dailyAt('23:30')->withoutOverlapping();

// Sistem peringatan dini, dijalankan setiap pagi
Schedule::command('peringatan:deteksi')->dailyAt('06:00')->withoutOverlapping();

// Detak scheduler untuk /health: bila berhenti berarti cron `schedule:run` tidak berjalan
Schedule::call(fn () => Cache::put('scheduler:detak', now(), now()->addHour()))->everyMinute()->name('detak-scheduler');

// Sinkronisasi data JF dari API instansi pembina BKN, mingguan (aktifkan dengan JFPEMBINA_SYNC_TERJADWAL=true).
// Putaran yang macet dilanjutkan otomatis oleh command-nya.
Schedule::command('jf:sync')->weeklyOn(0, '01:30')->withoutOverlapping()
    ->when(fn () => config('jfpembina.enabled') && config('jfpembina.scheduled'));

// Tarik bezetting dari API Dashboard setiap malam (aktifkan dengan DASHBOARD_JF_ENABLED=true dan DASHBOARD_JF_TERJADWAL=true)
Schedule::command('jf:tarik-dashboard')->dailyAt('02:30')->withoutOverlapping()
    ->when(fn () => config('dashboardjf.enabled') && config('dashboardjf.scheduled'));
