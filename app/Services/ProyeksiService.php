<?php

namespace App\Services;

use App\Enums\UsulanStatus;
use App\Models\AnjabAbk;
use App\Support\Referensi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Proyeksi kebutuhan pegawai 5 tahun berbasis ABK.
 *
 * Untuk setiap posisi (unit × jabatan) dengan ABK final terbaru:
 *  - kebutuhan tahun t  = beban kerja ABK × (1 + pertumbuhan%)^(t - tahun ABK) ÷ waktu kerja efektif
 *  - existing akhir t   = pegawai aktif saat ini − pegawai yang mencapai BUP s.d. 31 Desember t
 *  - kekurangan t       = max(0, kebutuhan t − existing akhir t)
 *  - rencana formasi t  = tambahan kekurangan dibanding tahun sebelumnya (tahun pertama:
 *                         kekurangan dikurangi formasi yang sudah ditetapkan tetapi belum terisi)
 */
class ProyeksiService
{
    public function tahunRencana(?int $mulai = null): array
    {
        $mulai ??= (int) now()->format('Y') + 1;

        return range($mulai, $mulai + Referensi::HORIZON_PROYEKSI_TAHUN - 1);
    }

    public function hitung(int $instansiId, array $filters = [], ?int $mulai = null): array
    {
        $tahun = $this->tahunRencana($mulai);
        $akhir = end($tahun);

        $abks = AnjabAbk::query()
            ->from('anjab_abks as a')
            ->select('a.*')
            ->where('a.instansi_id', $instansiId)
            ->where('a.status', 'final')
            ->whereRaw('a.tahun = (SELECT MAX(b.tahun) FROM anjab_abks b WHERE b.unit_kerja_id = a.unit_kerja_id AND b.jabatan_id = a.jabatan_id AND b.status = ?)', ['final'])
            ->when($filters['unit_ids'] ?? null, fn ($q, $ids) => $q->whereIn('a.unit_kerja_id', $ids))
            ->when($filters['jenis'] ?? null, fn ($q, $j) => $q->whereIn('a.jabatan_id', DB::table('jabatans')->where('jenis', $j)->select('id')))
            ->with(['unitKerja:id,nama', 'jabatan:id,nama,jenis,estimasi_biaya_tahunan'])
            ->get();

        if ($abks->isEmpty()) {
            return ['tahun' => $tahun, 'rows' => [], 'total' => $this->kosong($tahun)];
        }

        $keys = fn ($q) => $q->whereIn('unit_kerja_id', $abks->pluck('unit_kerja_id')->unique())
            ->whereIn('jabatan_id', $abks->pluck('jabatan_id')->unique());

        // existing & jumlah pensiun per tahun per posisi
        $pegawai = $keys(DB::table('pegawais'))
            ->where('is_active', true)
            ->get(['unit_kerja_id', 'jabatan_id', 'tmt_pensiun'])
            ->groupBy(fn ($p) => $p->unit_kerja_id.'-'.$p->jabatan_id);

        $formasi = $keys(DB::table('usulan_details as d')->join('usulans as u', 'u.id', '=', 'd.usulan_id'))
            ->where('u.status', UsulanStatus::Ditetapkan->value)
            ->groupBy('d.unit_kerja_id', 'd.jabatan_id')
            ->selectRaw('d.unit_kerja_id, d.jabatan_id, SUM(CASE WHEN COALESCE(d.jumlah_ditetapkan,0) > d.jumlah_terisi THEN COALESCE(d.jumlah_ditetapkan,0) - d.jumlah_terisi ELSE 0 END) as sisa')
            ->get()->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => (int) $r->sisa]);

        $rows = $abks->map(function (AnjabAbk $abk) use ($tahun, $pegawai, $formasi) {
            $key = $abk->unit_kerja_id.'-'.$abk->jabatan_id;
            $list = $pegawai->get($key, collect());
            $existing = $list->count();
            $sisaFormasi = $formasi[$key] ?? 0;

            $perTahun = [];
            $gapSebelum = null;
            foreach ($tahun as $t) {
                $batas = $t.'-12-31';
                $pensiun = $list->filter(fn ($p) => $p->tmt_pensiun && $p->tmt_pensiun <= $batas)->count();
                $kebutuhan = $abk->kebutuhanPadaTahun($t);
                $existingAkhir = $existing - $pensiun;
                $gap = max(0, $kebutuhan - $existingAkhir);
                $rencana = $gapSebelum === null ? max(0, $gap - $sisaFormasi) : max(0, $gap - $gapSebelum);
                $gapSebelum = $gapSebelum === null ? max($gap, $sisaFormasi) : max($gap, $gapSebelum);

                $perTahun[$t] = [
                    'kebutuhan' => $kebutuhan,
                    'existing' => $existingAkhir,
                    'pensiun' => $pensiun,
                    'kekurangan' => $gap,
                    'kelebihan' => max(0, $existingAkhir - $kebutuhan),
                    'rencana' => $rencana,
                ];
            }

            return [
                'anjab_id' => $abk->id,
                'unit_kerja_id' => $abk->unit_kerja_id,
                'unit_nama' => $abk->unitKerja?->nama,
                'jabatan_id' => $abk->jabatan_id,
                'jabatan_nama' => $abk->jabatan?->nama,
                'jenis' => $abk->jabatan?->jenis,
                'tahun_abk' => $abk->tahun,
                'pertumbuhan' => $abk->pertumbuhan_beban,
                'existing_saat_ini' => $existing,
                'kebutuhan_saat_ini' => $abk->kebutuhan,
                'formasi_belum_terisi' => $sisaFormasi,
                'biaya_per_orang' => (int) ($abk->jabatan?->estimasi_biaya_tahunan ?? 0),
                'tahun' => $perTahun,
                'total_rencana' => array_sum(array_column($perTahun, 'rencana')),
            ];
        })->sortBy([['unit_nama', 'asc'], ['jabatan_nama', 'asc']])->values();

        return ['tahun' => $tahun, 'rows' => $rows->all(), 'total' => $this->total($rows, $tahun)];
    }

    private function total(Collection $rows, array $tahun): array
    {
        $total = $this->kosong($tahun);

        foreach ($rows as $row) {
            foreach ($tahun as $t) {
                foreach (['kebutuhan', 'existing', 'pensiun', 'kekurangan', 'kelebihan', 'rencana'] as $k) {
                    $total[$t][$k] += $row['tahun'][$t][$k];
                }
                // estimasi tambahan belanja pegawai kumulatif untuk formasi yang direncanakan
                $total[$t]['anggaran'] += $row['tahun'][$t]['rencana'] * $row['biaya_per_orang'];
            }
        }

        return $total;
    }

    private function kosong(array $tahun): array
    {
        return array_fill_keys($tahun, ['kebutuhan' => 0, 'existing' => 0, 'pensiun' => 0, 'kekurangan' => 0, 'kelebihan' => 0, 'rencana' => 0, 'anggaran' => 0]);
    }
}
