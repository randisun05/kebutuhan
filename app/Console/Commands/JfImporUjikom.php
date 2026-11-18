<?php

namespace App\Console\Commands;

use App\Models\JfAsn;
use App\Models\RefInstansiBkn;
use App\Services\JfPembina\JfAsnSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Menyalin data JF (referensi instansi BKN + tabel jf_asn) dari database proyek ujikom-pusbin.
 * Dipakai selama API JF Pembina belum bisa diakses. Aman diulang (upsert per siasn_id / NIP);
 * baris yang source-nya 'manual' di sini tidak ditimpa.
 */
class JfImporUjikom extends Command
{
    protected $signature = 'jf:impor-ujikom
        {--koneksi=ujikom : nama koneksi database sumber (lihat UJIKOM_DB_* pada .env)}
        {--tanpa-nonaktif : jangan ikutkan ASN yang tidak aktif di ujikom}';

    protected $description = 'Salin data eksisting JF (instansi BKN + ASN JF) dari database ujikom-pusbin';

    public function handle(JfAsnSyncService $service): int
    {
        $sumber = DB::connection($this->option('koneksi'));
        try {
            $sumber->getPdo();
        } catch (Throwable $e) {
            $this->error('Tidak dapat terhubung ke database sumber: '.$e->getMessage());
            $this->line('Isi UJIKOM_DB_HOST, UJIKOM_DB_PORT, UJIKOM_DB_DATABASE, UJIKOM_DB_USERNAME, UJIKOM_DB_PASSWORD pada .env.');

            return self::FAILURE;
        }

        // 1. referensi instansi BKN: instansis ujikom yang memiliki siasn_id
        $peta = []; // id instansi ujikom => id ref_instansi_bkn
        $ref = 0;
        foreach ($sumber->table('instansis')->whereNotNull('siasn_id')->orderBy('id')->get() as $i) {
            $model = RefInstansiBkn::updateOrCreate(['siasn_id' => $i->siasn_id], [
                'nama' => $i->title,
                'jenis' => $i->siasn_jenis,
                'kode' => $i->siasn_kode,
                'jenis_instansi' => $i->siasn_jenis_instansi,
                'jf_asn_total' => $i->jf_asn_total,
                'jf_synced_at' => $i->jf_synced_at,
            ]);
            $peta[$i->id] = $model->id;
            $ref++;
        }
        $this->info("Referensi instansi BKN: {$ref} entri.");

        // 2. ASN JF
        $baru = $diperbarui = $dilewati = 0;
        $dukung = ['siasn_id', 'nama', 'jabatan_fungsional_id', 'jabatan_fungsional_nama', 'jabatan_pokok', 'jenjang',
            'unor_nama', 'gol_ruang', 'tmt_jabatan', 'kedudukan', 'detail_synced_at', 'detail_attempts',
            'is_active', 'source', 'first_seen_at', 'last_seen_at'];

        $query = $sumber->table('jf_asn')->orderBy('id');
        if ($this->option('tanpa-nonaktif')) {
            $query->where('is_active', true);
        }

        $bar = $this->output->createProgressBar((clone $query)->count());
        $query->chunkById(500, function ($rows) use ($peta, $dukung, &$baru, &$diperbarui, &$dilewati, $bar) {
            $ada = JfAsn::whereIn('nip', $rows->pluck('nip'))->get()->keyBy('nip');

            foreach ($rows as $r) {
                $refId = $peta[$r->instansi_id] ?? null;
                $bar->advance();
                if (! $refId) {
                    $dilewati++; // instansinya tidak punya siasn_id di ujikom

                    continue;
                }

                $data = collect($dukung)->mapWithKeys(fn ($k) => [$k => $r->{$k} ?? null])->all();
                $data['ref_instansi_id'] = $refId;
                $data['detail_attempts'] = (int) ($data['detail_attempts'] ?? 0);
                $data['is_active'] = (bool) $data['is_active'];
                $data['source'] = $data['source'] ?: 'bkn';

                $model = $ada->get($r->nip);
                if ($model && $model->source === 'manual') {
                    $dilewati++;
                } elseif ($model) {
                    $model->update($data);
                    $diperbarui++;
                } else {
                    JfAsn::create($data + ['nip' => $r->nip]);
                    $baru++;
                }
            }
        });
        $bar->finish();
        $this->newLine();
        $this->info("ASN JF: {$baru} baru, {$diperbarui} diperbarui, {$dilewati} dilewati.");

        // 3. petakan ke master aplikasi
        $instansi = $service->petakanInstansi();
        $jabatan = $service->petakanJabatan();
        $this->info("Pemetaan instansi: {$instansi['terpetakan']} terpetakan, {$instansi['belum']} belum.");
        $this->info("Pemetaan jabatan: {$jabatan['terpetakan']} terpetakan, {$jabatan['belum']} belum (isi jabatans.siasn_jabatan_id atau samakan nama).");

        return self::SUCCESS;
    }
}
