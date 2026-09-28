<?php

namespace App\Console\Commands;

use App\Models\Instansi;
use App\Services\Siasn\SiasnSyncService;
use Illuminate\Console\Command;

class SiasnSync extends Command
{
    protected $signature = 'siasn:sync
        {jenis=pegawai : pegawai atau unor}
        {--instansi=* : kode instansi (kosong = semua instansi yang memiliki ID SIASN)}
        {--nip=* : NIP tertentu (khusus jenis pegawai)}';

    protected $description = 'Sinkronisasi unit organisasi / data pegawai dari SIASN BKN';

    public function handle(SiasnSyncService $sync): int
    {
        $jenis = $this->argument('jenis');
        if (! in_array($jenis, ['pegawai', 'unor'], true)) {
            $this->error('Jenis harus "pegawai" atau "unor".');

            return self::INVALID;
        }

        $instansis = Instansi::where('is_active', true)
            ->when($this->option('instansi'), fn ($q, $kode) => $q->whereIn('kode', $kode),
                fn ($q) => $q->whereNotNull('siasn_instansi_id'))
            ->get();

        foreach ($instansis as $instansi) {
            $log = $jenis === 'unor'
                ? $sync->syncUnor($instansi)
                : $sync->syncPegawai($instansi, $this->option('nip') ?: null);

            $this->line(sprintf('%s: %s — %d/%d berhasil, %d gagal', $instansi->nama, $log->status, $log->berhasil, $log->total, $log->gagal));
            foreach (array_slice($log->pesan ?? [], 0, 10) as $pesan) {
                $this->line('  - '.$pesan);
            }
        }

        return self::SUCCESS;
    }
}
