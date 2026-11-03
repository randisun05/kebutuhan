<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // fitur AI memakai kuota gratis Gemini bersama: batasi per pengguna
        RateLimiter::for('ai', fn (Request $request) => [
            Limit::perMinute(6)->by('ai-menit:'.$request->user()?->id),
            Limit::perDay(100)->by('ai-hari:'.$request->user()?->id),
        ]);
    }
}
