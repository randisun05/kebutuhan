<?php

namespace Tests\Unit;

use App\Models\AnjabAbk;
use App\Models\AnjabUraianTugas;
use App\Support\Referensi;
use PHPUnit\Framework\TestCase;

/** Rumus inti ABK yang tidak memerlukan database. */
class RumusAbkTest extends TestCase
{
    public function test_kategori_prestasi_efektivitas_jabatan(): void
    {
        $this->assertNull(Referensi::pej(null));
        $this->assertSame('A', Referensi::pej(1.01)['nilai']);
        $this->assertSame('B', Referensi::pej(1.00)['nilai']);
        $this->assertSame('B', Referensi::pej(0.90)['nilai']);
        $this->assertSame('C', Referensi::pej(0.70)['nilai']);
        $this->assertSame('D', Referensi::pej(0.50)['nilai']);
        $this->assertSame('E', Referensi::pej(0.49)['nilai']);
    }

    public function test_satuan_periode_setara_jam_kerja_efektif(): void
    {
        // 1.250 jam/tahun = 6.250 menit/bulan = 1.500 menit/minggu = 300 menit/hari
        $wke = Referensi::WAKTU_KERJA_EFEKTIF;
        $this->assertSame(75000, $wke);
        $this->assertSame($wke, 6250 * Referensi::PERIODE_PER_TAHUN['bulan']);
        $this->assertSame($wke, 1500 * Referensi::PERIODE_PER_TAHUN['minggu']);
        $this->assertSame($wke, 300 * Referensi::PERIODE_PER_TAHUN['hari']);
    }

    public function test_beban_kerja_uraian_tugas_disetahunkan(): void
    {
        $t = new AnjabUraianTugas(['volume' => 4, 'satuan_periode' => 'minggu', 'norma_waktu' => 30]);

        $this->assertSame(200.0, (float) $t->volumeTahunan());
        $this->assertSame(6000.0, (float) $t->bebanKerja());
    }

    public function test_proyeksi_kebutuhan_dengan_pertumbuhan_beban(): void
    {
        $abk = new AnjabAbk(['tahun' => 2026, 'waktu_kerja_efektif' => 75000, 'pertumbuhan_beban' => 10]);
        $abk->total_beban_kerja = 300000; // 4 pegawai

        $this->assertSame(4, $abk->kebutuhanPadaTahun(2026));
        $this->assertSame(4, $abk->kebutuhanPadaTahun(2027));   // 4,4
        $this->assertSame(5, $abk->kebutuhanPadaTahun(2028));   // 4,84
        $this->assertSame(4, $abk->kebutuhanPadaTahun(2020));   // sebelum tahun ABK tidak menyusut
    }
}
