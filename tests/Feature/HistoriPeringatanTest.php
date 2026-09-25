<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UsulanStatus;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\Peringatan;
use App\Models\Usulan;
use App\Notifications\RingkasanPeringatan;
use App\Services\HistoriService;
use App\Services\LaporanService;
use App\Services\PeringatanService;
use App\Services\SnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class HistoriPeringatanTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    /** Unit A bergerak (ada mutasi 2 bulan lalu), unit B diam 1 tahun & kekurangan. */
    private function skenario(): array
    {
        $instansi = $this->instansi();
        $a = $this->unit($instansi, 'A');
        $b = $this->unit($instansi, 'B');
        $c = $this->unit($instansi, 'C');
        $jab = $this->jabatan('JF');
        $this->abk($a, $jab, 2);
        $this->abk($b, $jab, 5);
        $this->abk($c, $jab, 1);
        $this->pegawai($a, $jab, 2);
        $this->pegawai($b, $jab, 1);
        $this->pegawai($c, $jab, 1);
        DB::table('pegawai_riwayats')->update(['created_at' => now()->subYear()]);
        DB::table('pegawais')->update(['updated_at' => now()->subYear()]);

        $p = Pegawai::where('unit_kerja_id', $c->id)->first();
        $p->update(['unit_kerja_id' => $a->id]);
        DB::table('pegawai_riwayats')->where('pegawai_id', $p->id)->latest('id')->limit(1)->update(['created_at' => now()->subMonths(2)]);

        return [$instansi, $a, $b, $c];
    }

    public function test_analisis_tidak_bergerak_per_unit(): void
    {
        [$instansi, $a, $b, $c] = $this->skenario();

        $diam = app(HistoriService::class)->analisis('unit', ['instansi_id' => $instansi->id], 6, true);

        $this->assertSame([$b->id], $diam->pluck('unit_kerja_id')->all());
        $this->assertSame('kurang', $diam->first()['kondisi']);

        $semua = app(HistoriService::class)->analisis('unit', ['instansi_id' => $instansi->id], 6, false)->keyBy('unit_kerja_id');
        $this->assertSame(1, $semua[$a->id]['pergerakan']);
        $this->assertSame(1, $semua[$c->id]['pergerakan']); // pegawai keluar dari C juga pergerakan

        // periode 1 bulan: mutasi 2 bulan lalu tidak terhitung
        $this->assertCount(3, app(HistoriService::class)->analisis('unit', ['instansi_id' => $instansi->id], 1, true));
    }

    public function test_rekonstruksi_rekam_jejak_menelusuri_mutasi(): void
    {
        [$instansi, $a, , $c] = $this->skenario();

        app(SnapshotService::class)->rekonstruksi(4);
        $periodeLalu = now()->startOfMonth()->subMonths(3)->toDateString();
        $snap = DB::table('bezetting_snapshots')->where('periode', $periodeLalu)->pluck('existing', 'unit_kerja_id');

        // sebelum mutasi: A = 2, C = 1 ; sekarang A = 3, C = 0
        $this->assertSame(2, (int) $snap[$a->id]);
        $this->assertSame(1, (int) $snap[$c->id]);

        $tren = app(HistoriService::class)->tren(['instansi_id' => $instansi->id], 4);
        $this->assertCount(4, $tren);
        $this->assertSame(4, end($tren)['existing']);
    }

    public function test_peringatan_terdeteksi_ditindaklanjuti_dan_selesai_otomatis(): void
    {
        [$instansi, , $b] = $this->skenario();
        $admin = $this->user(Role::Admin);

        app(PeringatanService::class)->deteksi(false);
        $stagnan = Peringatan::where('kode', 'stagnan')->where('unit_kerja_id', $b->id)->firstOrFail();
        $this->assertSame('tinggi', $stagnan->tingkat);
        $this->assertTrue(Peringatan::where('kode', 'data_usang')->where('instansi_id', $instansi->id)->exists() === false); // ada perubahan 2 bln lalu
        $this->assertTrue(Peringatan::where('kode', 'kekurangan_kritis')->where('unit_kerja_id', $b->id)->exists()); // 1/5 = 20%

        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $this->actingAs($operator)->post("/peringatan/{$stagnan->id}/tindak-lanjut", ['catatan' => 'Diusulkan formasi 2027'])->assertSessionHasNoErrors();
        $this->assertSame('ditindaklanjuti', $stagnan->fresh()->status);

        // kondisi teratasi: 4 pegawai baru masuk unit B
        $this->pegawai($b, Jabatan::first(), 4);
        app(PeringatanService::class)->deteksi(false);
        $this->assertSame('selesai', $stagnan->fresh()->status);
        $this->assertNotNull($stagnan->fresh()->selesai_at);

        $this->actingAs($admin)->get('/peringatan')->assertOk();
        $this->actingAs($this->user(Role::Pimpinan))->post("/peringatan/{$stagnan->id}/tindak-lanjut", ['catatan' => 'x'])->assertForbidden();
    }

    public function test_notifikasi_ringkasan_peringatan_ke_admin_dan_operator(): void
    {
        Notification::fake();
        [$instansi] = $this->skenario();
        $admin = $this->user(Role::Admin);
        $operator = $this->user(Role::OperatorInstansi, $instansi);

        app(PeringatanService::class)->deteksi(true);

        Notification::assertSentTo([$admin, $operator], RingkasanPeringatan::class);
    }

    public function test_usulan_tertahan_terdeteksi(): void
    {
        [$instansi, $a] = $this->skenario();
        $usulan = Usulan::create(['instansi_id' => $instansi->id, 'tahun' => 2027, 'perihal' => 'X', 'status' => UsulanStatus::Diajukan]);
        $usulan->logs()->create(['aksi' => 'ajukan', 'ke_status' => 'diajukan'])->forceFill(['created_at' => now()->subDays(20)])->save();

        app(PeringatanService::class)->deteksi(false);

        $this->assertTrue(Peringatan::where('kode', 'usulan_tertahan')->where('kunci', "usulan_tertahan:{$usulan->id}:diajukan")->exists());
    }

    public function test_semua_laporan_dapat_dibuat_dan_diunduh(): void
    {
        [$instansi] = $this->skenario();
        app(PeringatanService::class)->deteksi(false);
        $this->actingAs($this->user(Role::Admin));

        foreach (array_keys(LaporanService::JENIS) as $jenis) {
            $this->get("/laporan?jenis={$jenis}&instansi_id={$instansi->id}")->assertOk()
                ->assertInertia(fn ($p) => $p->has('hasil.kolom')->has('hasil.baris'));
            $this->get("/laporan/unduh/pdf?jenis={$jenis}&instansi_id={$instansi->id}")->assertOk()->assertHeader('content-type', 'application/pdf');
        }

        Excel::fake();
        $this->get("/laporan/unduh/xlsx?jenis=rekap_unit&instansi_id={$instansi->id}")->assertOk();
        Excel::matchByRegex();
        Excel::assertDownloaded('/^laporan-rekap-unit-.*\.xlsx$/');
    }

    public function test_operator_hanya_melihat_data_instansinya(): void
    {
        [$instansi] = $this->skenario();
        $lain = $this->instansi('LAIN');
        $operator = $this->user(Role::OperatorInstansi, $instansi);

        $this->actingAs($operator)
            ->get("/laporan?jenis=rekap_instansi&instansi_id={$lain->id}")
            ->assertInertia(fn ($p) => $p->where('filters.instansi_id', $instansi->id));
        $this->get('/histori')->assertOk()->assertInertia(fn ($p) => $p->where('filters.instansi_id', $instansi->id));
        $this->get('/analitik')->assertOk();
    }

    public function test_halaman_analitik_histori_dapat_dibuka_semua_peran(): void
    {
        [$instansi] = $this->skenario();
        app(SnapshotService::class)->ambil();

        foreach ([Role::Admin, Role::VerifikatorBkn, Role::ValidatorKemenpan, Role::Pimpinan] as $role) {
            $this->actingAs($this->user($role));
            $this->get('/analitik')->assertOk();
            $this->get("/analitik?instansi_id={$instansi->id}")->assertOk();
            foreach (['instansi', 'unit', 'posisi'] as $level) {
                $this->get("/histori?level={$level}&bulan=6")->assertOk();
            }
            $this->get('/histori/export?level=unit')->assertOk();
            $this->get('/peringatan')->assertOk();
        }
    }
}
