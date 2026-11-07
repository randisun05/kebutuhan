<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pegawai;
use App\Services\KualitasDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class KualitasDataTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    private function jumlah(array $hasil, string $kode): int
    {
        return collect($hasil)->firstWhere('kode', $kode)['jumlah'];
    }

    public function test_pemeriksaan_mendeteksi_data_bermasalah(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $nonaktif = $this->unit($instansi, 'N');
        $nonaktif->update(['is_active' => false]);
        $jab = $this->jabatan('J');
        $this->pegawai($unit, $jab, 1);                       // sehat
        $this->pegawai($unit, $jab, 1, null);                 // tanpa tanggal lahir
        $this->pegawai($unit, $jab, 1, now()->subYears(10)->toDateString()); // umur 10
        $this->pegawai($nonaktif, $jab, 1);                   // unit nonaktif
        $this->pegawai($unit, $jab, 2, '1980-05-05');         // nama sama? ditimpa di bawah
        $kembar = Pegawai::where('tanggal_lahir', '1980-05-05')->get();
        $kembar->each->update(['nama' => 'Budi Santoso']);
        $pensiun = Pegawai::first();
        DB::table('pegawais')->where('id', $pensiun->id)->update(['tmt_pensiun' => now()->subMonth()->toDateString(), 'nip' => '123']);

        $hasil = app(KualitasDataService::class)->periksa($instansi->id);

        $this->assertSame(1, $this->jumlah($hasil, 'nip_tidak_valid'));
        $this->assertSame(1, $this->jumlah($hasil, 'tanggal_lahir_kosong'));
        $this->assertSame(1, $this->jumlah($hasil, 'umur_tidak_wajar'));
        $this->assertSame(1, $this->jumlah($hasil, 'lewat_pensiun'));
        $this->assertSame(1, $this->jumlah($hasil, 'unit_nonaktif'));
        $this->assertSame(2, $this->jumlah($hasil, 'kemungkinan_ganda'));
    }

    public function test_pegawai_nonaktif_tidak_diperiksa_dan_halaman_dibatasi_instansi(): void
    {
        $a = $this->instansi('A');
        $b = $this->instansi('B');
        $this->pegawai($this->unit($b, 'UB'), $this->jabatan('J'), 1, null);
        Pegawai::query()->update(['is_active' => false]);
        $this->assertSame(0, $this->jumlah(app(KualitasDataService::class)->periksa($b->id), 'tanggal_lahir_kosong'));

        $operator = $this->user(Role::OperatorInstansi, $a);
        $this->actingAs($operator)->get('/kualitas-data')->assertOk();
        // permintaan instansi lain dialihkan ke instansi operator sendiri
        $this->get("/kualitas-data?instansi_id={$b->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('instansiId', $a->id));
        $this->actingAs($this->user(Role::VerifikatorBkn))->get('/kualitas-data')->assertForbidden();
    }

    public function test_rincian_dipaginasi_difilter_unit_dan_dapat_diekspor(): void
    {
        $instansi = $this->instansi();
        $a = $this->unit($instansi, 'A');
        $b = $this->unit($instansi, 'B');
        $jab = $this->jabatan('J');
        $this->pegawai($a, $jab, 30, null);
        $this->pegawai($b, $jab, 4, null);
        $admin = $this->user(Role::Admin);

        // ringkasan memuat contoh terbatas, rincian memuat semuanya dengan pagination
        $hasil = app(KualitasDataService::class)->periksa($instansi->id);
        $this->assertSame(34, $this->jumlah($hasil, 'tanggal_lahir_kosong'));
        $this->assertCount(KualitasDataService::CONTOH, collect($hasil)->firstWhere('kode', 'tanggal_lahir_kosong')['contoh']);

        $this->actingAs($admin)->get("/kualitas-data?instansi_id={$instansi->id}&kode=tanggal_lahir_kosong")->assertOk()
            ->assertInertia(fn ($p) => $p->where('rincian.temuan.total', 34)->where('rincian.temuan.per_page', 25)->has('rincian.temuan.data', 25));
        $this->get("/kualitas-data?instansi_id={$instansi->id}&kode=tanggal_lahir_kosong&unit_id={$b->id}")
            ->assertInertia(fn ($p) => $p->where('rincian.temuan.total', 4));

        $this->get("/kualitas-data/export?instansi_id={$instansi->id}")->assertOk();
    }
}
