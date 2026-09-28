<?php

namespace App\Jobs;

use App\Models\Instansi;
use App\Services\Siasn\SiasnSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SinkronSiasn implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  string  $jenis  pegawai / unor
     */
    public function __construct(
        public int $instansiId,
        public string $jenis,
        public ?array $nips = null,
        public ?int $userId = null,
    ) {}

    public function handle(SiasnSyncService $sync): void
    {
        $instansi = Instansi::findOrFail($this->instansiId);

        $this->jenis === 'unor'
            ? $sync->syncUnor($instansi, $this->userId)
            : $sync->syncPegawai($instansi, $this->nips, $this->userId);
    }
}
