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
