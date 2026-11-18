<?php

namespace App\Jobs;

use App\Models\JfAsnSyncRun;
use App\Services\JfPembina\JfAsnSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Tahap 2 sinkronisasi JF: detail jabatan untuk satu potongan NIP, diambil paralel. Pendek dan idempotent:
 * NIP yang sudah terisi dilewati, jadi aman diulang antrian atau dilanjutkan. Gateway mati total ->
 * melempar error -> diulang dengan backoff; habis percobaan -> dicatat gagal, potongan lain tetap jalan,
 * dan NIP-nya diambil lagi pada putaran/lanjutan berikutnya.
 */
class SyncJfDetailChunkJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [120, 600];
    }

    /** @param  array<int, int>  $jfIds */
    public function __construct(public int $runId, public int $refInstansiId, public array $jfIds) {}

    public function handle(JfAsnSyncService $service): void
    {
        if ($run = JfAsnSyncRun::find($this->runId)) {
            $service->prosesDetail($run, $this->refInstansiId, $this->jfIds);
        }
    }

    public function failed(Throwable $e): void
    {
        app(JfAsnSyncService::class)->potonganDitinggalkan($this->runId, $this->refInstansiId, $this->jfIds, $e->getMessage());
    }
}
