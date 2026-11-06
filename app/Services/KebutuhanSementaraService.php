<?php

namespace App\Services;

use App\Enums\UsulanStatus;
use App\Models\KebutuhanSementara;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi kebutuhan sementara (jalur manual) dari jumlah pegawai existing atau dari formasi yang sudah ditetapkan.
 * Hanya posisi yang belum punya ABK final dan belum punya kebutuhan sementara yang diisi,
 * sehingga isian yang sudah ada tidak tertimpa.
 */
class KebutuhanSementaraService
{
    public const SUMBER = ['existing' => 'Existing', 'formasi' => 'Formasi ditetapkan'];

    /**
     * @param  array<int>|null  $unitIds  batasi ke unit kerja tertentu; null = seluruh instansi
     * @param  string  $sumber  existing (pegawai aktif) | formasi (jumlah ditetapkan pada usulan berstatus ditetapkan)
     * @return array{dibuat: int, dilewati_abk: int, dilewati_ada: int}
     */
    public function isi(int $instansiId, ?array $unitIds, ?int $userId, string $sumber = 'existing'): array
    {
        $posisi = $sumber === 'formasi'
            ? $this->posisiFormasi($instansiId, $unitIds)
            : $this->posisiExisting($instansiId, $unitIds);

        return $this->simpanPosisi($instansiId, $posisi, $userId, $sumber === 'formasi'
            ? 'Formasi ditetapkan per '.now()->format('d-m-Y')
            : 'Existing per '.now()->format('d-m-Y'));
    }

    public function isiDariExisting(int $instansiId, ?array $unitIds, ?int $userId): array
    {
        return $this->isi($instansiId, $unitIds, $userId, 'existing');
    }

    private function posisiExisting(int $instansiId, ?array $unitIds)
    {
        return DB::table('pegawais')
            ->where('is_active', true)
            ->where('instansi_id', $instansiId)
            ->when($unitIds !== null, fn ($q) => $q->whereIn('unit_kerja_id', $unitIds))
            ->groupBy('unit_kerja_id', 'jabatan_id')
            ->selectRaw('unit_kerja_id, jabatan_id, COUNT(*) as jumlah')
            ->get();
    }

    /** Total jumlah ditetapkan per unit + jabatan dari seluruh usulan instansi yang berstatus ditetapkan. */
    private function posisiFormasi(int $instansiId, ?array $unitIds)
    {
        return DB::table('usulan_details as d')
            ->join('usulans as u', 'u.id', '=', 'd.usulan_id')
            ->where('u.instansi_id', $instansiId)
            ->where('u.status', UsulanStatus::Ditetapkan->value)
            ->when($unitIds !== null, fn ($q) => $q->whereIn('d.unit_kerja_id', $unitIds))
            ->groupBy('d.unit_kerja_id', 'd.jabatan_id')
            ->selectRaw('d.unit_kerja_id, d.jabatan_id, COALESCE(SUM(d.jumlah_ditetapkan), 0) as jumlah')
            ->havingRaw('COALESCE(SUM(d.jumlah_ditetapkan), 0) > 0')
            ->get();
    }

    private function simpanPosisi(int $instansiId, $posisi, ?int $userId, string $dasar): array
    {
        $abk = DB::table('anjab_abks')->where('instansi_id', $instansiId)->where('status', 'final')
            ->get(['unit_kerja_id', 'jabatan_id'])->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => true]);
        $ada = KebutuhanSementara::where('instansi_id', $instansiId)
            ->get(['unit_kerja_id', 'jabatan_id'])->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => true]);

        $hasil = ['dibuat' => 0, 'dilewati_abk' => 0, 'dilewati_ada' => 0];
        DB::transaction(function () use ($posisi, $abk, $ada, $instansiId, $userId, $dasar, &$hasil) {
            foreach ($posisi as $p) {
                $kunci = $p->unit_kerja_id.'-'.$p->jabatan_id;
                if (isset($abk[$kunci])) {
                    $hasil['dilewati_abk']++;
                } elseif (isset($ada[$kunci])) {
                    $hasil['dilewati_ada']++;
                } else {
                    KebutuhanSementara::create([
                        'instansi_id' => $instansiId, 'unit_kerja_id' => $p->unit_kerja_id, 'jabatan_id' => $p->jabatan_id,
                        'jumlah' => (int) $p->jumlah, 'dasar' => $dasar, 'created_by' => $userId,
                    ]);
                    $hasil['dibuat']++;
                }
            }
        });

        return $hasil;
    }
}
