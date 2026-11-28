<?php

namespace Tests\Feature;

use App\Models\JfJabatanPokok;
use App\Models\KebutuhanFakta;
use App\Models\RefInstansiBkn;
use App\Models\RekomendasiJf;
use App\Models\SumberData;
use App\Services\Dashboard\DashboardJfClient;
use App\Services\Dashboard\DashboardJfException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

/** Klien API Dashboard (dapat dikonfigurasi) dan perintah jf:tarik-dashboard. */
class DashboardJfApiTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    private function konfigurasi(): void
    {
        config(['dashboardjf.enabled' => true, 'dashboardjf.base_url' => 'https://dash.test/api', 'dashboardjf.token' => 'rahasia']);
        SumberData::where('kode', 'bezetting_dashboard')->update(['is_active' => true]);
    }

    private function halaman(array $data, int $terakhir = 1): array
    {
        return ['per' => '2026-11-01', 'meta' => ['last_page' => $terakhir], 'data' => $data];
    }

    public function test_klien_menolak_bila_belum_aktif(): void
    {
        $this->expectException(DashboardJfException::class);
        app(DashboardJfClient::class)->ambil('BKN');
    }

    public function test_klien_membaca_semua_halaman_dengan_autentikasi_dan_field_terkonfigurasi(): void
    {
        $this->konfigurasi();
        config(['dashboardjf.respons.jumlah' => 'total']);
        Http::fake([
            'dash.test/api/jf/bezetting*' => Http::sequence()
                ->push($this->halaman([['instansi_nama' => 'A', 'jabatan' => 'x', 'jenjang' => 'muda', 'total' => '7']], 2))
                ->push($this->halaman([['instansi_nama' => 'B', 'jabatan' => 'y', 'jenjang' => 'madya', 'total' => 3]], 2)),
        ]);

        $hasil = app(DashboardJfClient::class)->ambil('BKN');

        $this->assertSame('2026-11-01', $hasil['per']);
        $this->assertSame([7, 3], array_column($hasil['baris'], 'jumlah'));
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer rahasia') && $r['pembina'] === 'BKN');
    }

    public function test_klien_melaporkan_akses_ditolak(): void
    {
        $this->konfigurasi();
        Http::fake(['dash.test/*' => Http::response([], 401)]);
        $this->expectExceptionMessage('menolak akses');
        app(DashboardJfClient::class)->ambil('BKN');
    }

    public function test_perintah_menulis_fakta_berversi_dan_melaporkan_yang_tak_cocok(): void
    {
        $this->konfigurasi();
        $analis = JfJabatanPokok::where('kode', 'analis')->firstOrFail();
        RefInstansiBkn::create(['siasn_id' => 'R9', 'nama' => 'Instansi Sembilan']);
        $data = [
            ['instansi_siasn_id' => 'R9', 'instansi_nama' => 'Instansi Sembilan', 'jabatan' => $analis->nama, 'jenjang' => 'Muda', 'jumlah' => 4],
            ['instansi_siasn_id' => 'R9', 'instansi_nama' => 'Instansi Sembilan', 'jabatan' => $analis->nama, 'jenjang' => 'Muda', 'jumlah' => 2],
            ['instansi_siasn_id' => 'R9', 'instansi_nama' => 'Instansi Sembilan', 'jabatan' => 'Jabatan Ghaib', 'jenjang' => 'Muda', 'jumlah' => 9],
        ];
        Http::fake(['dash.test/*' => function () use (&$data) {
            return Http::response($this->halaman($data));
        }]);

        $this->artisan('jf:tarik-dashboard --uji')->assertSuccessful();
        $this->assertSame(0, KebutuhanFakta::count());

        $this->artisan('jf:tarik-dashboard')->expectsOutputToContain('1 fakta baru')->expectsOutputToContain('Jabatan Ghaib')->assertSuccessful();
        $f = KebutuhanFakta::where('is_terbaru', true)->get();
        $this->assertCount(1, $f);
        $this->assertSame(6, $f->first()->nilai); // baris ganda dijumlahkan
        $this->assertSame(1, RekomendasiJf::count());

        // data sama: tidak ada versi baru; data berubah: versi lama menjadi riwayat
        $this->artisan('jf:tarik-dashboard')->expectsOutputToContain('0 fakta baru')->assertSuccessful();
        $data[0]['jumlah'] = 10;
        $this->artisan('jf:tarik-dashboard')->expectsOutputToContain('1 fakta baru')->assertSuccessful();
        $this->assertSame(2, KebutuhanFakta::count());
        $this->assertSame(12, KebutuhanFakta::where('is_terbaru', true)->value('nilai'));
    }
}
