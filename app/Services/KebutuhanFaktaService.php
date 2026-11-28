<?php

namespace App\Services;

use App\Models\KebutuhanFakta;
use App\Models\RekomendasiJf;

/** Menulis angka sebagai fakta berversi: versi baru hanya bila nilainya berubah; nilai lama ditandai bukan terbaru. */
class KebutuhanFaktaService
{
    /** @return int jumlah baris fakta baru (0 bila nilai sama) */
    public function simpan(RekomendasiJf $rekom, int $sumberId, int $jenjangId, int $nilai, ?int $imporId, ?string $tanggalData = null): int
    {
        $kunci = ['rekomendasi_jf_id' => $rekom->id, 'sumber_id' => $sumberId, 'jenjang_id' => $jenjangId, 'unit_kerja_id' => null];
        $terbaru = KebutuhanFakta::where($kunci)->where('is_terbaru', true)->first();
        if ($terbaru && $terbaru->nilai === $nilai) {
            return 0;
        }
        $terbaru?->update(['is_terbaru' => false]);
        KebutuhanFakta::create($kunci + ['nilai' => $nilai, 'impor_id' => $imporId, 'tanggal_data' => $tanggalData, 'is_terbaru' => true]);

        return 1;
    }
}
