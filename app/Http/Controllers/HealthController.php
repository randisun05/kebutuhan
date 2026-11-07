<?php

namespace App\Http\Controllers;

use App\Services\PanduanService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Pemeriksaan kesehatan untuk pemantauan produksi (uptime monitor / load balancer).
 * Tanpa login dan tanpa data sensitif; 503 hanya bila database tidak dapat diakses.
 */
class HealthController extends Controller
{
    /** Scheduler dianggap terlambat bila detak terakhir lebih lama dari ini (menit). */
    private const BATAS_SCHEDULER_MENIT = 5;

    public function __invoke()
    {
        $hasil = ['status' => 'ok', 'waktu' => now()->toIso8601String(), 'versi' => app(PanduanService::class)->versiTerbaru()];

        try {
            DB::select('select 1');
            $hasil['database'] = 'ok';
        } catch (Throwable) {
            return response()->json(['status' => 'gagal', 'database' => 'gagal', 'waktu' => $hasil['waktu']], 503);
        }

        // detak scheduler (php artisan schedule:run harus berjalan tiap menit lewat cron)
        $detak = Cache::get('scheduler:detak');
        $hasil['scheduler'] = $detak
            ? (Carbon::parse($detak)->diffInMinutes(now()) <= self::BATAS_SCHEDULER_MENIT ? 'ok' : 'terlambat')
            : 'belum_berjalan';

        // antrean: tunggakan dan job gagal (hanya bila memakai antrean database)
        if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
            $hasil['antrean'] = [
                'menunggu' => DB::table('jobs')->count(),
                'gagal' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
            ];
        }

        if ($hasil['scheduler'] !== 'ok' || ($hasil['antrean']['gagal'] ?? 0) > 0) {
            $hasil['status'] = 'perlu_perhatian';
        }

        return response()->json($hasil);
    }
}
