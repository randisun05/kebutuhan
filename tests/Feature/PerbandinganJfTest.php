<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ImporProfil;
use App\Models\JfJabatanPokok;
use App\Models\JfJenjang;
use App\Models\RefInstansiBkn;
use App\Models\RekomendasiJf;
use App\Models\SumberData;
use App\Services\PerbandinganJfService;
use App\Services\RekomendasiJfImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\Feature\Concerns\BuildsRekomendasiExcel;
use Tests\TestCase;

/** Perbandingan referensi: sumber impor, ABK, pegawai (dan Dashboard) berdampingan tanpa saling membatasi. */
class PerbandinganJfTest extends TestCase
{
    use BuildsKebutuhanData, BuildsRekomendasiExcel, RefreshDatabase;

    /** Instansi lokal "LOK" dengan ABK dan pegawai pada dua jabatan yang dikaitkan ke Analis Pertama dan Muda, serta rekomendasi dari Excel. */
    private function siapkan(bool $kaitkan = true): array
    {
        $instansi = $this->instansi('LOK');
        RefInstansiBkn::create(['siasn_id' => 'RL', 'nama' => 'Instansi Lokal', 'instansi_id' => $instansi->id]);
        $unit = $this->unit($instansi, 'Bagian Kepegawaian');
        $j1 = $this->jabatan('JF-T-1');
        $j2 = $this->jabatan('JF-T-2');
        if ($kaitkan) {
            $analis = JfJabatanPokok::where('kode', 'analis')->value('id');
            $j1->update(['jf_jabatan_pokok_id' => $analis, 'jf_jenjang_id' => JfJenjang::where('kode', 'pertama')->value('id')]);
            $j2->update(['jf_jabatan_pokok_id' => $analis, 'jf_jenjang_id' => JfJenjang::where('kode', 'muda')->value('id')]);
        }
        $this->abk($unit, $j1, 20);
        $this->abk($unit, $j2, 10);
        $this->pegawai($unit, $j1, 8);
        $this->pegawai($unit, $j2, 9);

        // bezetting saat rekom 22, rekom BKN 55, penetapan Menpan 53
        $path = $this->buatExcel([$this->baris('Instansi Lokal', 'Analis SDM Aparatur', [
            17 => 10, 18 => 12, 19 => 0, 20 => 0, 22 => 30, 23 => 20, 24 => 5, 25 => 0, 37 => 28, 38 => 20, 39 => 5, 40 => null,
        ])]);
        $svc = app(RekomendasiJfImportService::class);
        $svc->simpan($svc->baca($path, ImporProfil::firstOrFail()), null, 'x.xlsx');
        unlink($path);

        return [$instansi, RekomendasiJf::firstOrFail()];
    }

    public function test_semua_sumber_berdampingan_dan_penanda_berjauhan(): void
    {
        [, $h] = $this->siapkan();
        $baris = app(PerbandinganJfService::class)->baris($this->pembinaBkn()->id);

        $this->assertCount(1, $baris);
        $b = $baris->first();
        $this->assertSame([
            'bezetting_rekom' => 22, 'rekom_bkn' => 55, 'penetapan_menpan' => 53, 'abk_simonkeb' => 30, 'pegawai_simonkeb' => 17,
        ], $b['nilai']); // sumber Dashboard nonaktif (belum dikonfigurasi) tidak menjadi kolom
        // kebutuhan: 55 vs 30 = selisih 45% > 20%; bezetting: 22 vs 17 = 22,7% > 20%
        $this->assertSame(['kebutuhan' => true, 'bezetting' => true], $b['berjauhan']);

        // ambang dapat dinaikkan: tidak lagi berjauhan
        config(['simonkeb.perbandingan.ambang_beda_persen' => 60]);
        $this->assertSame(['kebutuhan' => false, 'bezetting' => false], app(PerbandinganJfService::class)->baris($this->pembinaBkn()->id)->first()['berjauhan']);
        $this->assertSame($h->id, $b['id']);
    }

    public function test_tanpa_kaitan_jabatan_atau_tanpa_instansi_lokal_nilai_langsung_kosong_bukan_nol(): void
    {
        $this->siapkan(false); // jabatan SIMONKEB belum dikaitkan ke JF
        $b = app(PerbandinganJfService::class)->baris($this->pembinaBkn()->id)->first();
        $this->assertNull($b['nilai']['abk_simonkeb']);
        $this->assertNull($b['nilai']['pegawai_simonkeb']);
        $this->assertSame(55, $b['nilai']['rekom_bkn']); // sumber impor tetap ada
        // tanpa sumber langsung, hanya rekom 55 dan penetapan 53 (selisih kecil) dan satu bezetting: tidak berjauhan
        $this->assertSame(['kebutuhan' => false, 'bezetting' => false], $b['berjauhan']);

        // alias yang belum dipetakan: sumber langsung tidak tersedia
        $path = $this->buatExcel([$this->baris('Instansi Tidak Dikenal', 'Analis SDM Aparatur')]);
        $svc = app(RekomendasiJfImportService::class);
        $svc->simpan($svc->baca($path, ImporProfil::firstOrFail()), null, 'y.xlsx');
        unlink($path);
        $asing = app(PerbandinganJfService::class)->baris($this->pembinaBkn()->id)->firstWhere('instansi', 'Instansi Tidak Dikenal');
        $this->assertFalse($asing['dipetakan']);
        $this->assertNull($asing['nilai']['abk_simonkeb']);
    }

    public function test_rincian_per_jenjang_dan_per_unit(): void
    {
        [, $h] = $this->siapkan();
        $r = app(PerbandinganJfService::class)->rincian($h);

        $pertama = collect($r['jenjang'])->firstWhere('kode', 'pertama');
        $this->assertEquals(['rekom_bkn' => 30, 'abk_simonkeb' => 20, 'pegawai_simonkeb' => 8, 'bezetting_rekom' => 10], array_intersect_key($pertama['nilai'], array_flip(['rekom_bkn', 'abk_simonkeb', 'pegawai_simonkeb', 'bezetting_rekom'])));
        $utama = collect($r['jenjang'])->firstWhere('kode', 'utama');
        $this->assertNull($utama['nilai']['abk_simonkeb']); // tidak ada jabatan yang dikaitkan ke Utama
        $this->assertCount(2, $r['unit']);
        $this->assertSame([20, 8], [$r['unit'][0]['abk'], $r['unit'][0]['pegawai']]);
        $this->assertSame(2, $r['jabatan_terkait']);
    }

    public function test_halaman_dan_pembatasan_akses(): void
    {
        [$instansi, $h] = $this->siapkan();
        $lain = $this->instansi('LAIN');
        $lan = $this->pembinaKedua('lan');

        // administrator
        $this->actingAs($this->user(Role::Admin))->get('/perbandingan-jf')->assertOk()
            ->assertInertia(fn ($p) => $p->where('ringkasan.baris', 1)->where('ringkasan.berjauhan_kebutuhan', 1)->has('sumber', 5)->has('datas.data', 1));
        $this->get('/perbandingan-jf?berjauhan=bezetting')->assertInertia(fn ($p) => $p->has('datas.data', 1));
        $this->get('/perbandingan-jf?q=tidak-ada')->assertInertia(fn ($p) => $p->has('datas.data', 0));
        $this->get("/perbandingan-jf/{$h->id}")->assertOk()->assertInertia(fn ($p) => $p->where('data.instansi', 'Instansi Lokal')->has('r.jenjang', 4)->has('r.unit', 2));

        // operator instansi: hanya instansinya
        $this->actingAs($this->user(Role::OperatorInstansi, $instansi))->get('/perbandingan-jf')->assertInertia(fn ($p) => $p->where('ringkasan.baris', 1));
        $this->get("/perbandingan-jf/{$h->id}")->assertOk();
        $this->actingAs($this->user(Role::OperatorInstansi, $lain))->get('/perbandingan-jf')->assertInertia(fn ($p) => $p->where('ringkasan.baris', 0));
        $this->get("/perbandingan-jf/{$h->id}")->assertForbidden();

        // pembina: hanya pembinanya sendiri
        $this->actingAs($this->penggunaPembina($this->pembinaBkn()))->get('/perbandingan-jf')->assertOk()->assertInertia(fn ($p) => $p->where('ringkasan.baris', 1));
        $this->get("/perbandingan-jf/{$h->id}")->assertOk();
        $this->actingAs($this->penggunaPembina($lan))->get("/perbandingan-jf/{$h->id}")->assertForbidden();
        $this->get('/perbandingan-jf?pembina_id='.$this->pembinaBkn()->id)->assertInertia(fn ($p) => $p->where('pembinaId', $lan->id)->where('ringkasan.baris', 0));
    }

    public function test_sumber_langsung_divalidasi_jenis_dan_keunikannya(): void
    {
        $this->actingAs($this->user(Role::Admin));
        $bkn = $this->pembinaBkn()->id;
        $dasar = ['pembina_id' => $bkn, 'tingkat' => 'unit', 'sifat' => 'hidup', 'is_active' => true];

        // sudah ada ABK bawaan: sumber ABK kedua ditolak; jenis harus sesuai adapter
        $this->post('/jf-master/sumber', $dasar + ['kode' => 'abk_lain', 'nama' => 'ABK lain', 'jenis' => 'kebutuhan', 'adapter' => 'abk'])->assertSessionHasErrors('adapter');
        $this->post('/jf-master/sumber', $dasar + ['kode' => 'peg_salah', 'nama' => 'Salah', 'jenis' => 'kebutuhan', 'adapter' => 'pegawai'])->assertSessionHasErrors('jenis');

        // pembina lain boleh punya sumber ABK sendiri
        $lan = $this->pembinaKedua('lan');
        $this->post('/jf-master/sumber', ['pembina_id' => $lan->id, 'kode' => 'abk_lan', 'nama' => 'ABK LAN', 'jenis' => 'kebutuhan', 'tingkat' => 'unit', 'sifat' => 'hidup', 'adapter' => 'abk', 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, SumberData::where('pembina_id', $lan->id)->where('adapter', 'abk')->count());
    }
}
