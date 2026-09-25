<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan rekam jejak kebutuhan vs existing per posisi untuk periode (bulan) berjalan.
 * Dijalankan harian lewat scheduler; baris bulan berjalan ditimpa sehingga
 * setiap bulan menyimpan kondisi terakhirnya.
 */
class SnapshotService
{
    public function __construct(private MonitoringService $monitoring) {}

    public function ambil(?Carbon $tanggal = null, ?int $instansiId = null): int
    {
        $periode = ($tanggal ?? now())->copy()->startOfMonth()->toDateString();
        $filters = $instansiId ? ['instansi_id' => $instansiId] : [];

        $rows = $this->monitoring->positionsQuery($filters)->get()->map(fn ($p) => [
            'periode' => $periode,
            'instansi_id' => $p->instansi_id,
            'unit_kerja_id' => $p->unit_kerja_id,
            'jabatan_id' => $p->jabatan_id,
            'kebutuhan' => (int) $p->kebutuhan,
            'existing' => (int) $p->existing,
            'pensiun' => (int) $p->pensiun,
            'formasi' => (int) $p->formasi,
            'diambil_at' => now(),
        ]);

        DB::transaction(function () use ($periode, $instansiId, $rows) {
            DB::table('bezetting_snapshots')->where('periode', $periode)
                ->when($instansiId, fn ($q) => $q->where('instansi_id', $instansiId))->delete();
            foreach ($rows->chunk(500) as $chunk) {
                DB::table('bezetting_snapshots')->insert($chunk->all());
            }
        });

        return $rows->count();
    }

    /**
     * Isi rekam jejak bulan-bulan sebelumnya (yang belum ada) dengan menelusuri mundur
     * riwayat pegawai: existing akhir bulan = existing kini − yang masuk setelahnya + yang keluar setelahnya.
     * Kebutuhan memakai ABK final saat ini (pendekatan).
     */
    public function rekonstruksi(int $bulan = 12, ?int $instansiId = null): int
    {
        $sekarang = $this->monitoring->positionsQuery($instansiId ? ['instansi_id' => $instansiId] : [])->get()
            ->keyBy(fn ($p) => $p->unit_kerja_id.'-'.$p->jabatan_id);
        $unitInstansi = DB::table('unit_kerjas')->pluck('instansi_id', 'id');

        $events = DB::table('pegawai_riwayats')
            ->where('created_at', '>=', now()->startOfMonth()->subMonths($bulan))
            ->orderByDesc('created_at')
            ->get(['jenis', 'dari_unit_id', 'dari_jabatan_id', 'ke_unit_id', 'ke_jabatan_id', 'created_at']);

        $existing = $sekarang->map(fn ($p) => (int) $p->existing)->all();
        $total = 0;

        for ($i = 0; $i < $bulan; $i++) {
            // kondisi pada akhir bulan (sekarang − i − 1)
            $akhir = now()->startOfMonth()->subMonths($i);
            $periode = $akhir->copy()->subMonth()->toDateString();

            foreach ($events as $e) {
                if ($e->created_at < $akhir->toDateTimeString() || $e->created_at >= $akhir->copy()->addMonth()->toDateTimeString()) {
                    continue;
                }
                $dari = $e->dari_unit_id ? $e->dari_unit_id.'-'.$e->dari_jabatan_id : null;
                $ke = $e->ke_unit_id ? $e->ke_unit_id.'-'.$e->ke_jabatan_id : null;

                // batalkan efek peristiwa untuk mundur satu bulan
                if ($e->jenis === 'masuk' && $ke) {
                    $existing[$ke] = ($existing[$ke] ?? 0) - 1;
                } elseif ($e->jenis === 'keluar' && $dari) {
                    $existing[$dari] = ($existing[$dari] ?? 0) + 1;
                } elseif ($dari && $ke && $dari !== $ke) {
                    $existing[$ke] = ($existing[$ke] ?? 0) - 1;
                    $existing[$dari] = ($existing[$dari] ?? 0) + 1;
                }
            }

            if (DB::table('bezetting_snapshots')->where('periode', $periode)
                ->when($instansiId, fn ($q) => $q->where('instansi_id', $instansiId))->exists()) {
                continue;
            }

            $rows = [];
            foreach ($existing as $key => $n) {
                [$unit, $jab] = array_map('intval', explode('-', $key));
                $inst = $unitInstansi[$unit] ?? null;
                if (! $inst || ($instansiId && $inst !== $instansiId)) {
                    continue;
                }
                $keb = (int) ($sekarang[$key]->kebutuhan ?? 0);
                if ($keb === 0 && $n <= 0) {
                    continue;
                }
                $rows[] = ['periode' => $periode, 'instansi_id' => $inst, 'unit_kerja_id' => $unit, 'jabatan_id' => $jab,
                    'kebutuhan' => $keb, 'existing' => max(0, $n), 'pensiun' => 0, 'formasi' => 0, 'diambil_at' => now()];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('bezetting_snapshots')->insert($chunk);
            }
            $total += count($rows);
        }

        return $total;
    }
}
