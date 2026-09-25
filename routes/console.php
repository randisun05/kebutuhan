<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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
