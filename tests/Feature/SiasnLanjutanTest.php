<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pegawai;
use App\Models\Peringatan;
use App\Models\SiasnJabatanPeta;
use App\Models\SiasnUnorPeta;
use App\Models\User;
use App\Services\PeringatanService;
use App\Services\Siasn\SiasnSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

/** Pemetaan unor lewat antarmuka, laporan hasil sinkron, dan peringatan baru. */
class SiasnLanjutanTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    private const NIP = '199201012020121001';

    private function fakeSiasn(): void
    {
        config([
            'siasn.enabled' => true, 'siasn.base_url' => 'https://siasn.test/apisiasn/1.0',
            'siasn.apim.url' => 'https://siasn.test/oauth2/token', 'siasn.apim.username' => 'u', 'siasn.apim.password' => 'p',
            'siasn.sso.url' => 'https://sso.test/token', 'siasn.sso.client_id' => 'c', 'siasn.sso.username' => 'u', 'siasn.sso.password' => 'p',
        ]);
        Http::fake([
            'siasn.test/oauth2/token' => Http::response(['access_token' => 'A']),
            'sso.test/token' => Http::response(['access_token' => 'S']),
            'siasn.test/apisiasn/1.0/pns/data-utama/*' => Http::response(['code' => 1, 'data' => [
                'id' => 'pns-1', 'nipBaru' => self::NIP, 'nama' => 'Pegawai', 'tglLahir' => '01-01-1992', 'unorId' => 'U-XYZ', 'unorNama' => 'Bidang Baru',
                'jabatanNama' => 'Jabatan J', 'kedudukanPnsNama' => 'Aktif',
            ]]),
        ]);
    }

    public function test_unor_belum_dipetakan_dicatat_lalu_dipetakan_lewat_antarmuka(): void
    {
        $this->fakeSiasn();
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'Unit Lokal');
        $this->jabatan('J');
        $sync = app(SiasnSyncService::class);

        $log = $sync->syncPegawai($instansi, [self::NIP]);
        $this->assertSame(1, $log->gagal);
        $peta = SiasnUnorPeta::firstOrFail();
        $this->assertSame(['U-XYZ', 'Bidang Baru', 1, null], [$peta->siasn_unor_id, $peta->nama_siasn, $peta->jumlah_pegawai, $peta->unit_kerja_id]);

        // operator instansi lain ditolak; unit instansi lain ditolak; operator sendiri boleh
        $this->actingAs($this->user(Role::OperatorInstansi, $this->instansi('LAIN')))
            ->put("/siasn/peta-unor/{$peta->id}", ['unit_kerja_id' => $unit->id])->assertForbidden();
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $unitLain = $this->unit($this->instansi('B'), 'X');
        $this->actingAs($operator)->put("/siasn/peta-unor/{$peta->id}", ['unit_kerja_id' => $unitLain->id])->assertForbidden();
        $this->put("/siasn/peta-unor/{$peta->id}", ['unit_kerja_id' => $unit->id])->assertSessionHas('success');

        $log = $sync->syncPegawai($instansi, [self::NIP]);
        $this->assertSame(1, $log->berhasil);
        $this->assertSame($unit->id, Pegawai::where('nip', self::NIP)->value('unit_kerja_id'));
    }

    public function test_hasil_sinkron_terjadwal_dilaporkan_ke_admin_dan_operator(): void
    {
        $this->fakeSiasn();
        $instansi = $this->instansi();
        $this->unit($instansi, 'U')->update(['siasn_unor_id' => 'U-XYZ']);
        $admin = $this->user(Role::Admin);
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $lain = User::factory()->create(['role' => Role::OperatorInstansi, 'instansi_id' => $this->instansi('B')->id, 'email' => 'b@test.local']);

        app(SiasnSyncService::class)->syncPegawai($instansi, [self::NIP]); // user null = terjadwal

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $operator->notifications()->count());
        $this->assertSame(0, $lain->notifications()->count());
        $this->assertStringContainsString('1 jabatan', $admin->notifications()->first()->data['pesan']);
    }

    public function test_sinkron_manual_hanya_melapor_ke_pemulanya(): void
    {
        $this->fakeSiasn();
        $instansi = $this->instansi();
        $this->unit($instansi, 'U')->update(['siasn_unor_id' => 'U-XYZ']);
        $admin = $this->user(Role::Admin);
        $operator = $this->user(Role::OperatorInstansi, $instansi);

        app(SiasnSyncService::class)->syncPegawai($instansi, [self::NIP], $operator->id);

        $this->assertSame(1, $operator->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_peringatan_peta_siasn_dan_kualitas_data(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('J');
        SiasnJabatanPeta::create(['kunci' => 'nama:x', 'nama_siasn' => 'X', 'instansi_id' => $instansi->id, 'jumlah_pegawai' => 12]);
        SiasnUnorPeta::create(['instansi_id' => $instansi->id, 'siasn_unor_id' => 'U1', 'jumlah_pegawai' => 3]);
        // 2 dari 10 pegawai tanpa tanggal lahir = 20% (>= 2 x ambang 5% -> tinggi)
        $this->pegawai($unit, $jab, 8);
        $this->pegawai($unit, $jab, 2, null);

        app(PeringatanService::class)->deteksi(false);

        $peta = Peringatan::where('kode', 'peta_siasn')->firstOrFail();
        $this->assertSame('tinggi', $peta->tingkat); // 15 pegawai >= ambang 10
        $this->assertSame(['jabatan' => 1, 'unor' => 1, 'pegawai' => 15], $peta->data);
        $kualitas = Peringatan::where('kode', 'kualitas_data')->firstOrFail();
        $this->assertSame(['bermasalah' => 2, 'persen' => 20], $kualitas->data);
        $this->assertSame('tinggi', $kualitas->tingkat);

        // setelah dipetakan/dibenahi, peringatan selesai otomatis
        DB::table('siasn_jabatan_peta')->update(['jabatan_id' => $jab->id]);
        DB::table('siasn_unor_peta')->update(['unit_kerja_id' => $unit->id]);
        Pegawai::query()->update(['tanggal_lahir' => '1990-01-01']);
        app(PeringatanService::class)->deteksi(false);
        $this->assertSame('selesai', $peta->fresh()->status);
        $this->assertSame('selesai', $kualitas->fresh()->status);
    }
}
