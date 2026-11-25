<?php

namespace App\Console\Commands;

use App\Models\RefInstansiBkn;
use App\Services\JfPembina\JfAsnSyncService;
use Illuminate\Console\Command;

class JfImporRef extends Command
{
    protected $signature = 'jf:impor-ref {berkas? : CSV id;nama;jenis;cepat_kode;jenis_instansi (bawaan database/data/bkn-instansi-ref.csv)}';

    protected $description = 'Impor referensi instansi BKN (instansiKerjaId) dan petakan ke master instansi aplikasi';

    public function handle(): int
    {
        $path = $this->argument('berkas') ?: database_path('data/bkn-instansi-ref.csv');
        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $fh = fopen($path, 'r');
        fgetcsv($fh, 0, ';'); // header
        $total = 0;
        while (($r = fgetcsv($fh, 0, ';')) !== false) {
            if (count($r) < 5 || $r[0] === '') {
                continue;
            }
            RefInstansiBkn::updateOrCreate(['siasn_id' => $r[0]], [
                'nama' => $r[1], 'jenis' => $r[2] ?: null, 'kode' => $r[3] ?: null, 'jenis_instansi' => $r[4] ?: null,
            ]);
            $total++;
        }
        fclose($fh);

        $peta = app(JfAsnSyncService::class)->petakanInstansi();
        $this->info("Referensi instansi BKN: {$total} entri; {$peta['terpetakan']} terpetakan ke instansi aplikasi, {$peta['belum']} belum.");

        return self::SUCCESS;
    }
}
