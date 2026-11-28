<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ImporProfil;
use App\Services\RekomendasiJfImportService;
use App\Services\WawasanRekomendasiJfService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\Feature\Concerns\BuildsRekomendasiExcel;
use Tests\TestCase;

/** Panel wawasan: angka dihitung dari data terkendali agar tiap aturan dapat diperiksa. */
class WawasanRekomendasiJfTest extends TestCase
{
    use BuildsKebutuhanData, BuildsRekomendasiExcel, RefreshDatabase;

    private const KOSONG = [17 => null, 18 => null, 19 => null, 20 => null, 22 => null, 23 => null, 24 => null, 25 => null, 37 => null, 38 => null, 39 => null, 40 => null,
        41 => null, 42 => null, 43 => null, 49 => null, 50 => null, 14 => null];

    private function isi(): void
    {
        $path = $this->buatExcel([
            // A: rekom + ditetapkan; jenjang bawah 22, dua jenjang teratas nol (tersumbat); Pranata dan Asesor belum usul
            $this->baris('Instansi A', 'Analis SDM Aparatur', [17 => 10, 18 => 12, 19 => 0, 20 => 0, 22 => 20, 23 => 20, 24 => 5, 25 => 1, 37 => 20, 38 => 18, 39 => 5, 40 => null]),
            [3 => 'Instansi A', 4 => 'Pranata SDM Aparatur', 7 => 'Belum Usul', 5 => 'KAB'] + self::KOSONG,
            [3 => 'Instansi A', 4 => 'Asesor SDM Aparatur', 7 => 'Belum Usul', 5 => 'KAB'] + self::KOSONG,
            // B: kementerian, bezetting kecil, direkom tetapi belum ditetapkan, surat rekomendasi lama
            $this->baris('Instansi B', 'Analis SDM Aparatur', [5 => 'KEMENT', 17 => 1, 18 => 1, 19 => 1, 20 => 0, 22 => 5, 23 => 5, 24 => 3, 25 => 1, 37 => null, 38 => null, 39 => null, 40 => null, 41 => null, 42 => null, 43 => null, 9 => '100/B', 10 => 45667]),
            $this->baris('Instansi B', 'Pranata SDM Aparatur', [5 => 'KEMENT', 17 => 0, 18 => 0, 19 => 0, 22 => 10, 23 => 5, 24 => 2, 37 => null, 38 => null, 39 => null, 40 => null, 41 => null, 42 => null, 43 => null]),
            // C: bezetting nol pada instansi yang sudah direkom; penetapan melebihi rekomendasi
            $this->baris('Instansi C', 'Analis SDM Aparatur', [17 => 0, 18 => 0, 19 => 0, 20 => 0, 22 => 8, 23 => 4, 24 => 2, 25 => 0, 37 => 10, 38 => 10, 39 => 6, 40 => null]),
            // D: belum usul sama sekali
            [3 => 'Instansi D', 4 => 'Analis SDM Aparatur', 7 => 'Belum Usul', 5 => 'KAB'] + self::KOSONG,
        ]);
        $svc = app(RekomendasiJfImportService::class);
        $svc->simpan($svc->baca($path, ImporProfil::firstOrFail()), null, 'x.xlsx');
        unlink($path);
    }

    public function test_aturan_tiap_panel(): void
    {
        $this->isi();
        $w = app(WawasanRekomendasiJfService::class)->hitung($this->pembinaBkn()->id, Carbon::parse('2026-11-28'));

        $this->assertTrue($w['lengkap']);
        $this->assertSame(['bezetting' => 'Bezetting saat rekomendasi', 'rekom' => 'Rekomendasi BKN', 'penetapan' => 'Penetapan KemenPANRB'], $w['peran']);

        // cakupan berdasarkan ada tidaknya angka, bukan teks status
        $analis = collect($w['cakupan'])->firstWhere('jabatan', 'Analis SDM Aparatur');
        $this->assertSame([4, 3, 2, 1], [$analis['instansi'], $analis['sudah_rekom'], $analis['sudah_ditetapkan'], $analis['belum_usul']]);
        $pranata = collect($w['cakupan'])->firstWhere('jabatan', 'Pranata SDM Aparatur');
        $this->assertSame([2, 1, 0], [$pranata['instansi'], $pranata['sudah_rekom'], $pranata['sudah_ditetapkan']]);

        // sasaran: B (3 < 5) kurang, C (0) nol, D belum ada data; A (22) bukan sasaran
        $this->assertSame(['nol' => 1, 'kurang' => 1, 'belum_ada_data' => 1, 'total_instansi' => 4], $w['sasaran']['ringkas']);
        $this->assertSame(['Instansi C', 'Instansi B'], array_column($w['sasaran']['daftar'], 'instansi'));
        $this->assertSame(['nol', 'kurang'], array_column($w['sasaran']['daftar'], 'kategori'));

        // jalur tersumbat: hanya A (bawah 22 >= 20, dua jenjang teratas nol)
        $this->assertSame(1, $w['tersumbat']['jumlah']);
        $this->assertSame(['Instansi A', 22, 0], [$w['tersumbat']['daftar'][0]['instansi'], $w['tersumbat']['daftar'][0]['bawah'], $w['tersumbat']['daftar'][0]['atas']]);

        // pendampingan: acuan Analis; hanya A yang sudah direkom Analis tetapi belum usul Asesor dan Pranata
        $this->assertSame('Analis SDM Aparatur', $w['pendampingan']['acuan']);
        $this->assertCount(1, $w['pendampingan']['daftar']);
        $this->assertSame(['Instansi A', 'Asesor SDM Aparatur, Pranata SDM Aparatur', 2], [$w['pendampingan']['daftar'][0]['instansi'], $w['pendampingan']['daftar'][0]['belum_usul'], $w['pendampingan']['daftar'][0]['jumlah']]);
        $kab = collect($w['pendampingan']['per_jenis'])->firstWhere('jenis', 'KAB');
        $this->assertSame(2, $kab['instansi']); // A dan C sudah direkom Analis
        $this->assertSame(1, $kab['belum']['Pranata SDM Aparatur']);

        // antrean: B Analis (surat lama, > 365 hari) dan B Pranata (tanpa surat/tanggal)
        $this->assertSame(2, $w['antrean']['jumlah']);
        $this->assertSame(['lebih_365' => 1, 'tanpa_tanggal' => 1], array_intersect_key($w['antrean']['kelompok'], ['lebih_365' => 1, 'tanpa_tanggal' => 1]));
        $this->assertSame('Analis SDM Aparatur', $w['antrean']['daftar'][0]['jabatan']); // terlama dahulu
        $this->assertSame('100/B', $w['antrean']['daftar'][0]['nomor']);

        // pemotongan: KAB rekom 46 + 14 = 60 vs penetapan 43 + 26 = 69 => 115% > 110% diperiksa
        $kabP = collect($w['pemotongan'])->firstWhere('jenis', 'KAB');
        $this->assertSame([2, 60, 69, 115.0, true], [$kabP['baris'], $kabP['rekom'], $kabP['penetapan'], $kabP['persen'], $kabP['periksa']]);
        $this->assertNull(collect($w['pemotongan'])->firstWhere('jenis', 'KEMENT'), 'B belum ditetapkan, jadi tidak masuk perbandingan');
    }

    public function test_ambang_dapat_diubah_lewat_konfigurasi(): void
    {
        $this->isi();
        config(['simonkeb.wawasan.ambang_sasaran' => 1, 'simonkeb.wawasan.ambang_tersumbat' => 50, 'simonkeb.wawasan.ambang_pemotongan' => 120]);
        $w = app(WawasanRekomendasiJfService::class)->hitung($this->pembinaBkn()->id, Carbon::parse('2026-11-28'));

        $this->assertSame(['nol' => 1, 'kurang' => 0], array_intersect_key($w['sasaran']['ringkas'], ['nol' => 1, 'kurang' => 1]));
        $this->assertSame(0, $w['tersumbat']['jumlah']);
        $this->assertFalse(collect($w['pemotongan'])->firstWhere('jenis', 'KAB')['periksa']);
    }

    public function test_halaman_unduhan_dan_pembatasan_akses(): void
    {
        $this->isi();
        $lan = $this->pembinaKedua('lan');
        $bkn = $this->pembinaBkn();

        // admin dan pengguna pembina sendiri
        $this->actingAs($this->user(Role::Admin))->get('/rekomendasi-jf/wawasan')->assertOk()->assertInertia(fn ($p) => $p->where('w.lengkap', true)->where('w.cakupan.0.instansi', 4)->has('pembinaOptions', 2));
        foreach (['sasaran', 'tersumbat', 'pendampingan', 'antrean'] as $panel) {
            $this->get("/rekomendasi-jf/wawasan/unduh/{$panel}?pembina_id={$bkn->id}")->assertOk();
        }
        $this->get('/rekomendasi-jf/wawasan/unduh/tidak-ada')->assertNotFound();

        $this->actingAs($this->penggunaPembina($bkn))->get('/rekomendasi-jf/wawasan')->assertOk()->assertInertia(fn ($p) => $p->where('pembinaId', $bkn->id));

        // pembina lain hanya melihat datanya sendiri (tanpa sumber bezetting: wawasan belum dapat dihitung), tidak data BKN
        $this->actingAs($this->penggunaPembina($lan))->get("/rekomendasi-jf/wawasan?pembina_id={$bkn->id}")->assertOk()
            ->assertInertia(fn ($p) => $p->where('pembinaId', $lan->id)->where('w.lengkap', false));
        $this->get("/rekomendasi-jf/wawasan/unduh/sasaran?pembina_id={$bkn->id}")->assertNotFound();

        // operator instansi tidak memakai panel ini
        $this->actingAs($this->user(Role::OperatorInstansi, $this->instansi()))->get('/rekomendasi-jf/wawasan')->assertForbidden();
        $this->get('/rekomendasi-jf/wawasan/unduh/sasaran')->assertForbidden();
    }
}
