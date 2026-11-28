<?php

namespace App\Console\Commands;

use App\Models\InstansiPembina;
use App\Services\Dashboard\DashboardJfException;
use App\Services\Dashboard\DashboardJfSyncService;
use Illuminate\Console\Command;

class JfTarikDashboard extends Command
{
    protected $signature = 'jf:tarik-dashboard
        {--pembina=* : kode instansi pembina (bawaan: semua pembina yang punya sumber adapter dashboard)}
        {--per= : tanggal data yang diminta ke API (bawaan: terbaru menurut API)}
        {--uji : hanya uji koneksi dan pencocokan, tanpa menulis data}';

    protected $description = 'Tarik data bezetting JF dari API Dashboard ke kebutuhan_fakta (sumber ber-adapter dashboard)';

    public function handle(DashboardJfSyncService $sync): int
    {
        $kode = array_filter((array) $this->option('pembina'));
        $pembinas = InstansiPembina::whereHas('sumber', fn ($q) => $q->where('adapter', 'dashboard'))
            ->when($kode, fn ($q) => $q->whereIn('kode', $kode))->get();
        if ($pembinas->isEmpty()) {
            $this->warn('Tidak ada pembina dengan sumber data ber-adapter "dashboard".');

            return self::SUCCESS;
        }

        $gagal = false;
        foreach ($pembinas as $p) {
            try {
                $h = $sync->jalankan($p, $this->option('per') ?: null, (bool) $this->option('uji'));
            } catch (DashboardJfException $e) {
                $this->error("[{$p->kode}] ".$e->getMessage());
                $gagal = true;

                continue;
            }
            $this->info("[{$p->kode}] {$h['baris']} baris diterima".(isset($h['siap']) ? ", {$h['siap']} siap ditulis (uji, tidak disimpan)" : ", {$h['fakta_baru']} fakta baru, {$h['header_baru']} header baru, {$h['alias_baru']} alias baru").'.');
            foreach (array_slice($h['tak_cocok'], 0, 20, true) as $k => $n) {
                $this->line("   tak cocok ({$n}×) {$k}");
            }
        }

        return $gagal ? self::FAILURE : self::SUCCESS;
    }
}
