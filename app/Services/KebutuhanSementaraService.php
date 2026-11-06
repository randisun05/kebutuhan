<?php

namespace App\Services;

use App\Models\KebutuhanSementara;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi kebutuhan sementara (jalur manual) dari jumlah pegawai existing.
 * Hanya posisi yang belum punya ABK final dan belum punya kebutuhan sementara yang diisi,
 * sehingga isian yang sudah ada tidak tertimpa.
 */
class KebutuhanSementaraService
{
    /**
     * @param  array<int>|null  $unitIds  batasi ke unit kerja tertentu; null = seluruh instansi
     * @return array{dibuat: int, dilewati_abk: int, dilewati_ada: int}
     */
    public function isiDariExisting(int $instansiId, ?array $unitIds, ?int $userId): array
    {
        $posisi = DB::table('pegawais')
            ->where('is_active', true)
            ->where('instansi_id', $instansiId)
            ->when($unitIds !== null, fn ($q) => $q->whereIn('unit_kerja_id', $unitIds))
            ->groupBy('unit_kerja_id', 'jabatan_id')
            ->selectRaw('unit_kerja_id, jabatan_id, COUNT(*) as jumlah')
            ->get();

        $abk = DB::table('anjab_abks')->where('instansi_id', $instansiId)->where('status', 'final')
            ->get(['unit_kerja_id', 'jabatan_id'])->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => true]);
        $ada = KebutuhanSementara::where('instansi_id', $instansiId)
            ->get(['unit_kerja_id', 'jabatan_id'])->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => true]);

        $hasil = ['dibuat' => 0, 'dilewati_abk' => 0, 'dilewati_ada' => 0];
        $dasar = 'Existing per '.now()->format('d-m-Y');

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
