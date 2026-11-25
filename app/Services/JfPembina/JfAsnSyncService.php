<?php

namespace App\Services\JfPembina;

use App\Jobs\SyncJfDetailChunkJob;
use App\Jobs\SyncJfInstansiJob;
use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\JfAsn;
use App\Models\JfAsnSyncItem;
use App\Models\JfAsnSyncRun;
use App\Models\RefInstansiBkn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Sinkronisasi data JF bidang manajemen ASN dari API instansi pembina BKN ke tabel jf_asn,
 * disalin dari JfAsnSyncService ujikom-pusbin. Dirancang TAHAN PUTUS:
 *
 *  - list-asn-pembinajf hanya memberi {id, nip, nama}; jabatan/jenjang/unit diambil dari data-utama per NIP.
 *  - Satu putaran (jf_asn_sync_runs) = satu item per instansi BKN (jf_asn_sync_items).
 *    Tahap 1 (SyncJfInstansiJob): daftar ASN per instansi (upsert, idempotent).
 *    Tahap 2 (SyncJfDetailChunkJob): detail per potongan NIP, diambil paralel.
 *  - Checkpoint ada di data: ASN dengan detail_synced_at terisi tidak diambil lagi; item yang selesai
 *    tidak diulang saat putaran dilanjutkan (lanjutkan()).
 *  - ASN yang hilang dari daftar ditandai is_active=false (tidak dihapus), hanya bila seluruh halaman
 *    instansi itu berhasil diambil. Baris source 'manual' tidak pernah ditimpa/dinonaktifkan sinkron.
 *  - Putaran tanpa denyut (updated_at) > stale_minutes dianggap macet (worker mati) dan bisa dilanjutkan.
 */
class JfAsnSyncService
{
    public function __construct(private JfPembinaClient $client) {}

    public function sedangBerjalan(): bool
    {
        return JfAsnSyncRun::where('status', 'running')
            ->where('updated_at', '>', now()->subMinutes(config('jfpembina.stale_minutes')))->exists();
    }

    /** Putaran 'running' yang lama tidak berdenyut (worker mati/terputus): kandidat dilanjutkan. */
    public function putaranMacet(): ?JfAsnSyncRun
    {
        return JfAsnSyncRun::where('status', 'running')
            ->where('updated_at', '<=', now()->subMinutes(config('jfpembina.stale_minutes')))->latest('id')->first();
    }

    /**
     * ID BKN (siasn_id) instansi yang gagal pada putaran terakhir yang selesai, untuk diulang.
     *
     * @return array<int, string>
     */
    public function instansiGagal(?JfAsnSyncRun $run = null): array
    {
        $run ??= JfAsnSyncRun::where('status', '!=', 'running')->latest('id')->first();
        $ids = collect($run?->failed_instansi ?? [])->pluck('id')->all();

        return $ids ? RefInstansiBkn::whereIn('id', $ids)->pluck('siasn_id')->all() : [];
    }

    /**
     * Mulai satu putaran dan antrikan satu job per instansi BKN.
     *
     * @param  array<int, string>|null  $hanyaSiasnIds  batasi ke ID instansi BKN tertentu
     */
    public function mulai(bool $denganDetail = true, ?int $userId = null, ?array $hanyaSiasnIds = null): JfAsnSyncRun
    {
        $ids = RefInstansiBkn::query()
            ->when($hanyaSiasnIds, fn ($q) => $q->whereIn('siasn_id', $hanyaSiasnIds))
            ->orderBy('id')->pluck('id');

        // putaran macet ditinggalkan (bisa dilanjutkan lewat lanjutkan() SEBELUM memulai yang baru)
        JfAsnSyncRun::where('status', 'running')
            ->where('updated_at', '<=', now()->subMinutes(config('jfpembina.stale_minutes')))
            ->update(['status' => 'failed', 'finished_at' => now()]);

        $run = JfAsnSyncRun::create([
            'status' => $ids->isEmpty() ? 'failed' : 'running',
            'with_detail' => $denganDetail,
            'instansi_total' => $ids->count(),
            'user_id' => $userId,
            'started_at' => now(),
        ]);

        // putaran baru = kesempatan baru untuk NIP yang tadinya terus gagal
        JfAsn::where('detail_attempts', '>', 0)->update(['detail_attempts' => 0]);

        // semua item dibuat dulu supaya putaran tidak tertutup lebih awal (antrian sync menjalankan job langsung)
        foreach ($ids as $id) {
            JfAsnSyncItem::create(['run_id' => $run->id, 'ref_instansi_id' => $id]);
        }
        foreach ($ids as $id) {
            SyncJfInstansiJob::dispatch($run->id, $id);
        }

        return $run;
    }

    /** Lanjutkan putaran macet: hanya item yang belum 'done' diantrikan lagi. */
    public function lanjutkan(JfAsnSyncRun $run): int
    {
        $items = JfAsnSyncItem::where('run_id', $run->id)->where('status', '!=', 'done')->get();

        JfAsnSyncRun::whereKey($run->id)->update(['status' => 'running', 'finished_at' => null, 'updated_at' => now()]);
        foreach ($items as $item) {
            $item->update(['status' => 'pending', 'chunks_total' => 0, 'chunks_done' => 0, 'error' => null]);
        }
        foreach ($items as $item) {
            SyncJfInstansiJob::dispatch($run->id, $item->ref_instansi_id);
        }
        $this->segarkanRun($run->id);

        return $items->count();
    }

    // ------------------------------------------------------------------ tahap 1: daftar ASN per instansi

    /** Dipanggil SyncJfInstansiJob. Exception tak terduga dilempar supaya antrian mengulang job. */
    public function prosesInstansi(JfAsnSyncRun $run, int $refInstansiId): void
    {
        $item = JfAsnSyncItem::firstOrCreate(['run_id' => $run->id, 'ref_instansi_id' => $refInstansiId]);
        if ($item->status === 'done') {
            return; // job ganda / sisa antrian setelah lanjutkan()
        }

        $item->update(['status' => 'listing']);
        $item->increment('attempts');
        $run->touch();

        $ref = RefInstansiBkn::find($refInstansiId);
        if (! $ref) {
            $this->gagalkanItem($run->id, $refInstansiId, 'referensi instansi BKN tidak ditemukan');

            return;
        }

        if (! $this->sinkronDaftar($run, $ref)) {
            $this->gagalkanItem($run->id, $refInstansiId, 'daftar ASN gagal diambil dari BKN (gateway timeout/reset)');

            return;
        }

        $ids = $run->with_detail ? JfAsn::butuhDetail()->where('ref_instansi_id', $refInstansiId)->pluck('id')->all() : [];
        if ($ids === []) {
            $item->update(['status' => 'done', 'error' => null]);
            $this->segarkanRun($run->id);

            return;
        }

        $potongan = array_chunk($ids, (int) config('jfpembina.chunk'));
        $item->update(['status' => 'details', 'chunks_total' => count($potongan), 'chunks_done' => 0, 'error' => null]);
        foreach ($potongan as $chunk) {
            try {
                SyncJfDetailChunkJob::dispatch($run->id, $refInstansiId, $chunk);
            } catch (Throwable $e) {
                // hanya terjadi di antrian 'sync' (job dijalankan langsung): kegagalan sudah dicatat failed()
                Log::warning("JfAsnSync: potongan detail instansi {$refInstansiId} gagal saat dijalankan langsung: ".$e->getMessage());
            }
        }
        $this->segarkanRun($run->id);
    }

    /** @return bool true bila seluruh halaman berhasil diambil */
    private function sinkronDaftar(JfAsnSyncRun $run, RefInstansiBkn $ref): bool
    {
        $halaman = (int) config('jfpembina.halaman');
        $offset = 0;

        do {
            $page = $this->client->daftarAsn($ref->siasn_id, $halaman, $offset);
            if ($page === null) {
                return false;
            }
            $total = $page['total'];

            foreach ($page['rows'] as $row) {
                $nip = trim((string) ($row['nip'] ?? ''));
                if ($nip === '') {
                    continue;
                }
                $ada = JfAsn::where('nip', $nip)->first();
                if ($ada && $ada->source === 'manual') {
                    continue; // diisi/diubah admin: tidak ditimpa sinkron
                }

                $atribut = [
                    'siasn_id' => $row['id'] ?? null,
                    'nama' => $row['nama'] ?? '',
                    'ref_instansi_id' => $ref->id,
                    'instansi_id' => $ref->instansi_id,
                    'is_active' => true,
                    'last_seen_at' => now(),
                ];
                if ($ada) {
                    $ada->update($atribut + ['source' => 'bkn']); // baris 'import' diambil alih BKN
                } else {
                    JfAsn::create($atribut + ['nip' => $nip, 'first_seen_at' => now()]);
                    $run->increment('asn_new');
                }
                $run->increment('asn_seen');
            }

            $offset += $halaman;
            // halaman kosong padahal masih ada sisa = gateway tidak lengkap; jangan anggap sukses
            // (mencegah penonaktifan massal)
            if ($page['rows'] === [] && $offset < $total) {
                return false;
            }
        } while ($offset < $total);

        // hanya aman menonaktifkan bila SEMUA halaman sukses
        $hilang = JfAsn::where('ref_instansi_id', $ref->id)->where('is_active', true)->where('source', 'bkn')
            ->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $run->started_at))
            ->update(['is_active' => false]);
        if ($hilang > 0) {
            $run->increment('asn_deactivated', $hilang);
        }

        $ref->update(['jf_asn_total' => $total, 'jf_synced_at' => now()]);

        return true;
    }

    // ------------------------------------------------------------------ tahap 2: detail per potongan NIP

    /**
     * Dipanggil SyncJfDetailChunkJob. Bila SEMUA permintaan gagal (gateway mati) dilempar RuntimeException
     * sebelum apa pun dicatat, supaya antrian mengulang dengan backoff alih-alih menghabiskan percobaan tiap NIP.
     *
     * @param  array<int, int>  $jfIds
     */
    public function prosesDetail(JfAsnSyncRun $run, int $refInstansiId, array $jfIds): void
    {
        $rows = JfAsn::butuhDetail()->whereIn('id', $jfIds)->get();

        if ($rows->isNotEmpty()) {
            $run->touch();
            $detail = $this->client->detailBanyak($rows->pluck('nip')->all());

            if ($rows->every(fn (JfAsn $a) => ($detail[$a->nip] ?? null) === null)) {
                throw new RuntimeException('BKN tidak merespons untuk seluruh potongan detail ('.$rows->count().' NIP).');
            }

            $pemetaan = $this->petaJabatan();
            $berhasil = $gagal = 0;
            foreach ($rows as $a) {
                $d = $detail[$a->nip] ?? null;
                if ($d === null) {
                    $a->increment('detail_attempts');
                    $gagal++;

                    continue;
                }
                [$pokok, $jenjang] = JfAsn::pisahJabatan($d['jabatan_fungsional_nama']);
                $a->update([
                    'jabatan_fungsional_id' => $d['jabatan_fungsional_id'],
                    'jabatan_fungsional_nama' => $d['jabatan_fungsional_nama'],
                    'jabatan_pokok' => $pokok ?: null,
                    'jenjang' => $jenjang,
                    'jabatan_id' => $this->cariJabatan($pemetaan, $d['jabatan_fungsional_id'], $d['jabatan_fungsional_nama']),
                    'unor_nama' => $d['unor_nama'],
                    'gol_ruang' => $d['gol_ruang'],
                    'tmt_jabatan' => $d['tmt_jabatan'],
                    'kedudukan' => $d['kedudukan'],
                    'detail_synced_at' => now(),
                    'detail_attempts' => 0,
                ]);
                $berhasil++;
            }
            $berhasil && $run->increment('detail_fetched', $berhasil);
            $gagal && $run->increment('detail_failed', $gagal);
        }

        $this->potonganSelesai($run->id, $refInstansiId);
    }

    /**
     * Job detail gagal permanen (percobaan antrian habis): catat gagal, tetapi item tetap bisa selesai.
     *
     * @param  array<int, int>  $jfIds
     */
    public function potonganDitinggalkan(int $runId, int $refInstansiId, array $jfIds, string $alasan): void
    {
        $rows = JfAsn::butuhDetail()->whereIn('id', $jfIds)->get();
        foreach ($rows as $a) {
            $a->increment('detail_attempts');
        }
        if ($rows->isNotEmpty()) {
            JfAsnSyncRun::whereKey($runId)->increment('detail_failed', $rows->count());
        }
        JfAsnSyncItem::where('run_id', $runId)->where('ref_instansi_id', $refInstansiId)
            ->update(['error' => 'sebagian detail gagal: '.mb_substr($alasan, 0, 300)]);
        Log::warning("JfAsnSync: potongan detail instansi {$refInstansiId} ditinggalkan: {$alasan}");

        $this->potonganSelesai($runId, $refInstansiId);
    }

    private function potonganSelesai(int $runId, int $refInstansiId): void
    {
        $item = JfAsnSyncItem::where('run_id', $runId)->where('ref_instansi_id', $refInstansiId)->first();
        if (! $item) {
            return;
        }
        $item->increment('chunks_done');
        if ($item->fresh()->chunks_done >= $item->chunks_total) {
            JfAsnSyncItem::whereKey($item->id)->where('status', 'details')->update(['status' => 'done']);
        }
        $this->segarkanRun($runId);
    }

    public function gagalkanItem(int $runId, int $refInstansiId, string $alasan): void
    {
        JfAsnSyncItem::where('run_id', $runId)->where('ref_instansi_id', $refInstansiId)
            ->update(['status' => 'failed', 'error' => mb_substr($alasan, 0, 500)]);
        $this->segarkanRun($runId);
    }

    /** Hitung ulang ringkasan putaran dari item-itemnya; tutup putaran bila semua item sudah final. */
    public function segarkanRun(int $runId): void
    {
        $run = JfAsnSyncRun::find($runId);
        if (! $run) {
            return;
        }

        $perStatus = JfAsnSyncItem::where('run_id', $runId)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $gagal = JfAsnSyncItem::where('run_id', $runId)->where('status', 'failed')->with('refInstansi:id,nama')->get();
        $selesai = (int) ($perStatus['done'] ?? 0);
        $belumFinal = (int) $perStatus->except(JfAsnSyncItem::SELESAI)->sum();

        $update = [
            'instansi_done' => $selesai + $gagal->count(),
            'failed_instansi' => $gagal->isEmpty() ? null : json_encode($gagal->map(fn ($i) => ['id' => $i->ref_instansi_id, 'nama' => $i->refInstansi?->nama])->values()),
            'updated_at' => now(),
        ];
        if ($belumFinal === 0 && $run->status === 'running' && $perStatus->sum() > 0) {
            $update['status'] = $selesai === 0 ? 'failed' : 'done';
            $update['finished_at'] = now();
        }

        // update terarah (bukan save()) supaya penghitung yang di-increment job lain tidak tertimpa
        JfAsnSyncRun::whereKey($runId)->update($update);
    }

    // ------------------------------------------------------------------ pemetaan ke master aplikasi

    /**
     * Petakan referensi instansi BKN ke master instansi aplikasi: lewat siasn_instansi_id, lalu lewat
     * nama ternormalisasi. Hasilnya disebarkan ke jf_asn.instansi_id. Tidak membuat instansi baru.
     *
     * @return array{terpetakan: int, belum: int}
     */
    public function petakanInstansi(): array
    {
        $bySiasn = Instansi::whereNotNull('siasn_instansi_id')->pluck('id', 'siasn_instansi_id');
        $byNama = Instansi::get(['id', 'nama'])->mapWithKeys(fn ($i) => [RefInstansiBkn::normalisasiNama($i->nama) => $i->id]);

        $terpetakan = 0;
        foreach (RefInstansiBkn::all() as $ref) {
            $id = $bySiasn[$ref->siasn_id] ?? $byNama[RefInstansiBkn::normalisasiNama($ref->nama)] ?? null;
            if ($id !== $ref->instansi_id) {
                $ref->update(['instansi_id' => $id]);
            }
            $id && $terpetakan++;
        }

        // sebarkan ke jf_asn per instansi (satu UPDATE per instansi terpetakan; portabel SQLite/MySQL)
        DB::transaction(function () {
            JfAsn::query()->update(['instansi_id' => null]);
            foreach (RefInstansiBkn::whereNotNull('instansi_id')->get(['id', 'instansi_id']) as $ref) {
                JfAsn::where('ref_instansi_id', $ref->id)->update(['instansi_id' => $ref->instansi_id]);
            }
        });

        return ['terpetakan' => $terpetakan, 'belum' => RefInstansiBkn::count() - $terpetakan];
    }

    /**
     * Petakan jabatan fungsional BKN ke master jabatan: lewat jabatans.siasn_jabatan_id, lalu lewat
     * nama persis (tanpa memperhatikan huruf besar/kecil). Hasilnya diisikan ke jf_asn.jabatan_id.
     *
     * @return array{terpetakan: int, belum: int}
     */
    public function petakanJabatan(): array
    {
        $peta = $this->petaJabatan();

        $terpetakan = 0;
        $belum = 0;
        $pasangan = JfAsn::whereNotNull('jabatan_fungsional_nama')
            ->select('jabatan_fungsional_id', 'jabatan_fungsional_nama')->distinct()->get();

        foreach ($pasangan as $p) {
            $id = $this->cariJabatan($peta, $p->jabatan_fungsional_id, $p->jabatan_fungsional_nama);
            JfAsn::where('jabatan_fungsional_nama', $p->jabatan_fungsional_nama)
                ->where(fn ($q) => $p->jabatan_fungsional_id === null
                    ? $q->whereNull('jabatan_fungsional_id') : $q->where('jabatan_fungsional_id', $p->jabatan_fungsional_id))
                ->update(['jabatan_id' => $id]);
            $id ? $terpetakan++ : $belum++;
        }

        return ['terpetakan' => $terpetakan, 'belum' => $belum];
    }

    /** @return array{siasn: array<string, int>, nama: array<string, int>} */
    private function petaJabatan(): array
    {
        return [
            'siasn' => Jabatan::whereNotNull('siasn_jabatan_id')->pluck('id', 'siasn_jabatan_id')->all(),
            'nama' => Jabatan::pluck('id', 'nama')->mapWithKeys(fn ($id, $nama) => [mb_strtolower(trim($nama)) => $id])->all(),
        ];
    }

    private function cariJabatan(array $peta, ?string $siasnId, ?string $nama): ?int
    {
        return ($siasnId !== null ? ($peta['siasn'][$siasnId] ?? null) : null)
            ?? ($nama !== null ? ($peta['nama'][mb_strtolower(trim($nama))] ?? null) : null);
    }
}
