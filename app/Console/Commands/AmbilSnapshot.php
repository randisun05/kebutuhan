<?php

namespace App\Console\Commands;

use App\Services\SnapshotService;
use Illuminate\Console\Command;

class AmbilSnapshot extends Command
{
    protected $signature = 'kebutuhan:snapshot
        {--instansi= : ID instansi tertentu}
        {--rekonstruksi= : isi mundur N bulan sebelumnya dari riwayat pegawai}';

    protected $description = 'Simpan rekam jejak kebutuhan vs existing per posisi untuk bulan berjalan';

    public function handle(SnapshotService $snapshot): int
    {
        if ($bulan = (int) $this->option('rekonstruksi')) {
            $r = $snapshot->rekonstruksi($bulan, $this->option('instansi') ? (int) $this->option('instansi') : null);
            $this->info("Rekonstruksi {$bulan} bulan: {$r} baris rekam jejak ditambahkan.");
        }

        $n = $snapshot->ambil(null, $this->option('instansi') ? (int) $this->option('instansi') : null);
        $this->info('Rekam jejak '.now()->format('m/Y')." disimpan: {$n} posisi.");

        return self::SUCCESS;
    }
}
