<?php

namespace App\Console\Commands;

use App\Models\RefInstansiBkn;
use App\Services\JfPembina\JfAsnSyncService;
use App\Services\JfPembina\JfPembinaClient;
use Illuminate\Console\Command;

class JfSync extends Command
{
    protected $signature = 'jf:sync
        {--only=* : batasi ke ID instansi BKN (siasn_id) tertentu (boleh diulang)}
        {--resume : lanjutkan putaran yang macet/terputus (item yang sudah selesai tidak diulang)}
        {--retry-failed : hanya ulang instansi yang gagal pada putaran terakhir}
        {--no-detail : lewati pengambilan detail jabatan per NIP}';

    protected $description = 'Sinkronkan data JF bidang manajemen ASN dari API instansi pembina BKN';

    public function handle(JfAsnSyncService $service, JfPembinaClient $client): int
    {
        if (! RefInstansiBkn::exists()) {
            $this->warn('Referensi instansi BKN kosong. Jalankan dulu: php artisan jf:impor-ref');

            return self::SUCCESS;
        }

        // putaran macet (worker mati/terputus) dilanjutkan, bukan dimulai dari nol, termasuk oleh jadwal mingguan
        if ($macet = $service->putaranMacet()) {
            $n = $service->lanjutkan($macet);
            $macet = $macet->fresh();
            $this->info("Melanjutkan putaran #{$macet->id}: {$n} instansi belum selesai diantrikan lagi (status {$macet->status}, detail {$macet->detail_fetched}).");

            return self::SUCCESS;
        }
        if ($this->option('resume')) {
            $this->warn('Tidak ada putaran yang macet untuk dilanjutkan.');

            return self::SUCCESS;
        }
        if ($service->sedangBerjalan()) {
            $this->warn('Sinkronisasi sebelumnya masih berjalan, dilewati.');

            return self::SUCCESS;
        }
        if (! $client->isConfigured()) {
            $this->error('Integrasi JF Pembina belum dikonfigurasi (JFPEMBINA_ENABLED dan kredensial JFPEMBINA_*/SIASN_* pada .env).');

            return self::FAILURE;
        }

        $only = $this->option('only') ?: null;
        if ($this->option('retry-failed')) {
            $only = $service->instansiGagal();
            if ($only === []) {
                $this->info('Tidak ada instansi gagal pada putaran terakhir.');

                return self::SUCCESS;
            }
        }

        $run = $service->mulai(! $this->option('no-detail'), null, $only)->fresh();
        $this->info("Sinkronisasi #{$run->id}: {$run->instansi_total} instansi, status {$run->status}, ASN dilihat {$run->asn_seen}, baru {$run->asn_new}, detail {$run->detail_fetched} (gagal {$run->detail_failed}).");
        if (config('queue.default') !== 'sync') {
            $this->line('Job diantrikan; jalankan worker: php artisan queue:work');
        }

        return self::SUCCESS;
    }
}
