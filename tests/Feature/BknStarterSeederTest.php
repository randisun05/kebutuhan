<?php

namespace Tests\Feature;

use App\Models\ImporProfil;
use App\Models\InstansiAlias;
use App\Models\InstansiPembina;
use App\Models\JfJabatanPokok;
use App\Models\JfJenjang;
use App\Models\KebutuhanFakta;
use App\Models\RefInstansiBkn;
use App\Models\RekomendasiJf;
use App\Models\SumberData;
use App\Models\User;
use Database\Seeders\BknStarterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\Feature\Concerns\BuildsRekomendasiExcel;
use Tests\TestCase;

/** Data awal produksi khusus BKN: aman dijalankan berulang, tanpa akun demo, tidak menimpa perubahan admin. */
class BknStarterSeederTest extends TestCase
{
    use BuildsKebutuhanData, BuildsRekomendasiExcel, RefreshDatabase;

    private function pakaiRekap(array $baris): string
    {
        $path = $this->buatExcel($baris);
        config(['simonkeb.starter.rekap_path' => $path, 'simonkeb.starter.impor_rekap' => true]);

        return $path;
    }

    public function test_starter_menyiapkan_master_referensi_alias_dan_angka_bkn(): void
    {
        $path = $this->pakaiRekap([
            $this->baris('Pemerintah Kab. Gunungkidul', 'Analis SDM Aparatur'),
            $this->baris('Pemerintah Kab. Kepulauan Mentawai', 'Pranata SDM Aparatur'),
            $this->baris('Kejaksaan Republik Indonesia', 'Asesor SDM Aparatur'),
            $this->baris('Badan Intelijen Negara', 'Analis SDM Aparatur'),
        ]);
        $pengguna = User::count();

        $this->seed(BknStarterSeeder::class);

        // master BKN lengkap
        $this->assertSame(4, JfJabatanPokok::where('pembina_id', $this->pembinaBkn()->id)->count());
        $this->assertSame(6, SumberData::where('pembina_id', $this->pembinaBkn()->id)->count()); // 3 impor + ABK, pegawai, dan Dashboard
        $this->assertSame(1, ImporProfil::where('kode', 'rekap-jfmasn')->count());
        // referensi instansi dari CSV dan tiga alias bawaan; yang tidak ada di referensi (BIN) tetap menunggu pemetaan admin
        $this->assertGreaterThan(600, RefInstansiBkn::count());
        foreach (['Pemerintah Kab. Gunungkidul' => 'Pemerintah Kab. Gunung Kidul', 'Pemerintah Kab. Kepulauan Mentawai' => 'Pemerintah Kab. Kep. Mentawai', 'Kejaksaan Republik Indonesia' => 'Kejaksaan Agung'] as $excel => $ref) {
            $this->assertSame($ref, InstansiAlias::where('nama', $excel)->firstOrFail()->refInstansi->nama, $excel);
        }
        $this->assertNull(InstansiAlias::where('nama', 'Badan Intelijen Negara')->value('ref_instansi_id'));
        // angka rekomendasi BKN, penetapan Menpan, dan bezetting saat rekomendasi masuk
        $this->assertSame(4, RekomendasiJf::count());
        foreach (['bezetting_rekom', 'rekom_bkn', 'penetapan_menpan'] as $kode) {
            $this->assertTrue(KebutuhanFakta::whereHas('sumber', fn ($q) => $q->where('kode', $kode))->exists(), $kode);
        }
        // tidak membuat akun apa pun (bukan data demo)
        $this->assertSame($pengguna, User::count());
        unlink($path);
    }

    public function test_starter_idempoten_dan_tidak_menimpa_perubahan_admin(): void
    {
        $path = $this->pakaiRekap([$this->baris('Instansi A', 'Analis SDM Aparatur')]);
        $this->seed(BknStarterSeeder::class);
        $hitung = fn () => [InstansiPembina::count(), JfJabatanPokok::count(), SumberData::count(), ImporProfil::count(), RefInstansiBkn::count(), InstansiAlias::count(), RekomendasiJf::count(), KebutuhanFakta::count()];
        $awal = $hitung();

        // admin mengubah nama sumber dan jenjang jabatan; seeder tidak boleh mengembalikannya
        SumberData::where('kode', 'rekom_bkn')->update(['nama' => 'Rekom BKN (diubah admin)']);
        $analis = JfJabatanPokok::where('kode', 'analis')->firstOrFail();
        $analis->jenjangs()->detach(JfJenjang::where('kode', 'utama')->value('id'));

        $this->seed(BknStarterSeeder::class);

        $this->assertSame($awal, $hitung());
        $this->assertSame('Rekom BKN (diubah admin)', SumberData::where('kode', 'rekom_bkn')->value('nama'));
        $this->assertSame(3, $analis->jenjangs()->count());
        unlink($path);
    }

    public function test_starter_melewati_rekap_bila_berkas_tidak_ada_atau_dimatikan(): void
    {
        config(['simonkeb.starter.rekap_path' => '/tidak/ada.xlsx', 'simonkeb.starter.impor_rekap' => true]);
        $this->seed(BknStarterSeeder::class);
        $this->assertSame(0, RekomendasiJf::count());
        $this->assertSame(4, JfJabatanPokok::count()); // master tetap tersedia

        $path = $this->pakaiRekap([$this->baris('Instansi A', 'Analis SDM Aparatur')]);
        config(['simonkeb.starter.impor_rekap' => false]);
        $this->seed(BknStarterSeeder::class);
        $this->assertSame(0, RekomendasiJf::count());
        unlink($path);
    }
}
