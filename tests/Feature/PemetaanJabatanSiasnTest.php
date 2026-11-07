<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pegawai;
use App\Models\SiasnJabatanPeta;
use App\Services\Siasn\SiasnSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

/** Jabatan SIASN yang belum punya padanan dicatat saat sinkron, lalu dipetakan administrator. */
class PemetaanJabatanSiasnTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    private const NIP = '199201012020121001';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'siasn.enabled' => true, 'siasn.base_url' => 'https://siasn.test/apisiasn/1.0',
            'siasn.apim.url' => 'https://siasn.test/oauth2/token', 'siasn.apim.username' => 'u', 'siasn.apim.password' => 'p',
            'siasn.sso.url' => 'https://sso.test/token', 'siasn.sso.client_id' => 'c', 'siasn.sso.username' => 'u', 'siasn.sso.password' => 'p',
        ]);
        Http::fake([
            'siasn.test/oauth2/token' => Http::response(['access_token' => 'A']),
            'sso.test/token' => Http::response(['access_token' => 'S']),
            'siasn.test/apisiasn/1.0/pns/data-utama/*' => Http::response(['code' => 1, 'data' => [
                'id' => 'pns-1', 'nipBaru' => self::NIP, 'nama' => 'Pegawai', 'tglLahir' => '01-01-1992', 'unorId' => 'U-1',
                'jabatanFungsionalId' => 'JF-9', 'jabatanNama' => 'Analis Tak Dikenal', 'kedudukanPnsNama' => 'Aktif',
            ]]),
        ]);
    }

    public function test_jabatan_tak_dikenal_dicatat_lalu_dipakai_setelah_dipetakan(): void
    {
        $instansi = $this->instansi();
        $this->unit($instansi, 'U')->update(['siasn_unor_id' => 'U-1']);
        $jab = $this->jabatan('JF-LOKAL');
        $sync = app(SiasnSyncService::class);

        $log = $sync->syncPegawai($instansi, [self::NIP]);
        $this->assertSame(1, $log->gagal);
        $this->assertSame(0, Pegawai::count());
        $peta = SiasnJabatanPeta::firstOrFail();
        $this->assertSame(['id:JF-9', 'JF-9', 1], [$peta->kunci, $peta->siasn_jabatan_id, $peta->jumlah_pegawai]);
        $this->assertNull($peta->jabatan_id);

        // hanya admin yang boleh memetakan
        $this->actingAs($this->user(Role::OperatorInstansi, $instansi))
            ->put("/siasn/peta-jabatan/{$peta->id}", ['jabatan_id' => $jab->id])->assertForbidden();
        $this->actingAs($this->user(Role::Admin))
            ->put("/siasn/peta-jabatan/{$peta->id}", ['jabatan_id' => $jab->id])->assertSessionHas('success');

        $log = $sync->syncPegawai($instansi, [self::NIP]);
        $this->assertSame(1, $log->berhasil);
        $this->assertSame($jab->id, Pegawai::where('nip', self::NIP)->value('jabatan_id'));
        // pemetaan yang sudah ada tidak ditimpa catatan baru
        $this->assertSame($jab->id, $peta->fresh()->jabatan_id);
    }

    public function test_halaman_siasn_memuat_daftar_peta(): void
    {
        SiasnJabatanPeta::create(['kunci' => 'nama:analis', 'nama_siasn' => 'Analis', 'jumlah_pegawai' => 3]);
        $this->actingAs($this->user(Role::Admin))->get('/siasn')->assertOk()
            ->assertInertia(fn ($page) => $page->has('petaJabatan', 1)->has('jabatanOptions'));
    }

    public function test_saran_pemetaan_berdasarkan_kemiripan_nama(): void
    {
        $this->jabatan('JF-ASDM-1')->update(['nama' => 'Analis Sumber Daya Manusia Aparatur Ahli Pertama']);
        $this->jabatan('JF-LAIN')->update(['nama' => 'Dokter Gigi Ahli Madya']);
        SiasnJabatanPeta::create(['kunci' => 'nama:a', 'nama_siasn' => 'ANALIS SUMBER DAYA MANUSIA APARATUR AHLI PERTAMA', 'jumlah_pegawai' => 2]);
        SiasnJabatanPeta::create(['kunci' => 'nama:b', 'nama_siasn' => 'Pilot Helikopter', 'jumlah_pegawai' => 1]);

        $this->actingAs($this->user(Role::Admin))->get('/siasn')->assertInertia(fn ($page) => $page
            ->where('petaJabatan.0.saran.nama', 'Analis Sumber Daya Manusia Aparatur Ahli Pertama')
            ->where('petaJabatan.0.saran.skor', 100)
            ->where('petaJabatan.1.saran', null));
    }
}
