<?php

namespace App\Console\Commands;

use App\Services\PeringatanService;
use Illuminate\Console\Command;

class DeteksiPeringatan extends Command
{
    protected $signature = 'peringatan:deteksi {--tanpa-notifikasi}';

    protected $description = 'Jalankan aturan sistem peringatan dini kebutuhan ASN';

    public function handle(PeringatanService $service): int
    {
        $hasil = $service->deteksi(! $this->option('tanpa-notifikasi'));
        $this->info("Peringatan baru: {$hasil['baru']}, aktif: {$hasil['aktif']}, selesai: {$hasil['selesai']}.");

        return self::SUCCESS;
    }
}
