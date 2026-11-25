<?php

namespace App\Jobs;

use App\Models\JfAsnSyncRun;
use App\Services\JfPembina\JfAsnSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Tahap 1 sinkronisasi JF: daftar ASN satu instansi BKN, lalu mengantrikan job detail per potongan NIP
 * (SyncJfDetailChunkJob). Pendek (puluhan detik); lihat JfAsnSyncService untuk desain tahan putus.
 */
class SyncJfInstansiJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function __construct(public int $runId, public int $refInstansiId) {}

    public function handle(JfAsnSyncService $service): void
    {
        if ($run = JfAsnSyncRun::find($this->runId)) {
            $service->prosesInstansi($run, $this->refInstansiId);
        }
    }

    public function failed(Throwable $e): void
    {
        app(JfAsnSyncService::class)->gagalkanItem($this->runId, $this->refInstansiId, 'job gagal: '.$e->getMessage());
    }
}
