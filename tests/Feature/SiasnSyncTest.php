<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pegawai;
use App\Models\SiasnSyncLog;
use App\Models\UnitKerja;
use App\Services\Siasn\SiasnSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class SiasnSyncTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'siasn.enabled' => true,
            'siasn.base_url' => 'https://siasn.test/apisiasn/1.0',
            'siasn.apim.url' => 'https://siasn.test/oauth2/token',
            'siasn.apim.username' => 'apim-user',
            'siasn.apim.password' => 'apim-pass',
            'siasn.sso.url' => 'https://sso.test/token',
            'siasn.sso.client_id' => 'client',
            'siasn.sso.username' => 'sso-user',
            'siasn.sso.password' => 'sso-pass',
        ]);
    }

    private function fakeSiasn(array $pns = []): void
    {
        Http::fake([
            'siasn.test/oauth2/token' => Http::response(['access_token' => 'APIM-TOKEN']),
            'sso.test/token' => Http::response(['access_token' => 'SSO-TOKEN']),
            'siasn.test/apisiasn/1.0/referensi/ref-unor' => Http::response(['code' => 1, 'data' => [
                ['Id' => 'U-ROOT', 'NamaUnor' => 'Sekretariat Daerah', 'DiatasanId' => '', 'InstansiId' => 'INS-1', 'EselonId' => '21', 'NamaJabatan' => 'Sekretaris Daerah'],
                ['Id' => 'U-BKD', 'NamaUnor' => 'BKPSDM', 'DiatasanId' => 'U-ROOT', 'InstansiId' => 'INS-1', 'EselonId' => '22'],
                ['Id' => 'U-LAIN', 'NamaUnor' => 'Unor instansi lain', 'DiatasanId' => '', 'InstansiId' => 'INS-2'],
            ]]),
            'siasn.test/apisiasn/1.0/pns/data-utama/*' => function (Request $request) use ($pns) {
                $nip = basename(parse_url($request->url(), PHP_URL_PATH));

                return isset($pns[$nip]) ? Http::response(['code' => 1, 'data' => $pns[$nip]]) : Http::response(['code' => 0, 'data' => null], 404);
            },
        ]);
    }

    public function test_sinkron_unor_membentuk_hierarki_unit_kerja(): void
    {
        $instansi = $this->instansi();
        $instansi->update(['siasn_instansi_id' => 'INS-1']);
        $this->fakeSiasn();

        $log = app(SiasnSyncService::class)->syncUnor($instansi);

        $this->assertSame('selesai', $log->status);
        $this->assertSame(2, $log->berhasil);
        $bkd = UnitKerja::where('siasn_unor_id', 'U-BKD')->firstOrFail();
        $this->assertSame('II', $bkd->eselon);
        $this->assertSame(UnitKerja::where('siasn_unor_id', 'U-ROOT')->value('id'), $bkd->parent_id);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer APIM-TOKEN') && $r->hasHeader('Auth', 'Bearer SSO-TOKEN'));
    }

    public function test_sinkron_pegawai_memperbarui_mencatat_mutasi_dan_menambah_pegawai_baru(): void
    {
        $instansi = $this->instansi();
        $lama = $this->unit($instansi, 'Lama');
        $baru = $this->unit($instansi, 'Baru');
        $baru->update(['siasn_unor_id' => 'U-BKD']);
        $jf = $this->jabatan('JF');
        $jf->update(['siasn_jabatan_id' => 'JF-SIASN-1']);
        $this->pegawai($lama, $jf, 1);
        $nipLama = Pegawai::value('nip');

        $this->fakeSiasn([
            $nipLama => ['id' => 'pns-1', 'nipBaru' => $nipLama, 'nama' => 'Nama dari SIASN', 'tglLahir' => '15-08-1985', 'unorId' => 'U-BKD',
                'jabatanFungsionalId' => 'JF-SIASN-1', 'golRuangAkhir' => 'III/c', 'kedudukanPnsNama' => 'Aktif'],
            '199201012020121001' => ['id' => 'pns-2', 'nipBaru' => '199201012020121001', 'nama' => 'Pegawai Baru', 'tglLahir' => '01-01-1992',
                'unorId' => 'U-BKD', 'jabatanNama' => 'Jabatan JF', 'kedudukanPnsNama' => 'Aktif'],
            '199301012020121001' => ['nipBaru' => '199301012020121001', 'nama' => 'Belum dipetakan', 'unorId' => 'U-TIDAK-ADA', 'kedudukanPnsNama' => 'Aktif'],
        ]);

        $log = app(SiasnSyncService::class)->syncPegawai($instansi, [$nipLama, '199201012020121001', '199301012020121001', '199401012020121001']);

        $this->assertSame('selesai', $log->status);
        $this->assertSame(2, $log->berhasil);
        $this->assertSame(2, $log->gagal);

        $p = Pegawai::where('nip', $nipLama)->firstOrFail();
        $this->assertSame($baru->id, $p->unit_kerja_id);
        $this->assertSame('Nama dari SIASN', $p->nama);
        $this->assertSame('1985-08-15', $p->tanggal_lahir->toDateString());
        $this->assertSame('siasn', $p->sumber);
        $this->assertSame('mutasi_unit', $p->riwayats()->first()->jenis);
        $this->assertSame('Sinkronisasi SIASN', $p->riwayats()->first()->keterangan);

        // jabatan dicocokkan lewat nama bila ID SIASN belum dipetakan
        $this->assertSame($jf->id, Pegawai::where('nip', '199201012020121001')->value('jabatan_id'));
    }

    public function test_pegawai_pensiun_di_siasn_menjadi_nonaktif(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $this->pegawai($unit, $jab, 1);
        $nip = Pegawai::value('nip');
        $this->fakeSiasn([$nip => ['nipBaru' => $nip, 'nama' => 'X', 'kedudukanPnsNama' => 'Pensiun']]);

        app(SiasnSyncService::class)->syncPegawai($instansi);

        $this->assertFalse(Pegawai::first()->is_active);
    }

    public function test_token_gagal_dicatat_sebagai_sinkron_gagal(): void
    {
        $instansi = $this->instansi();
        Http::fake(['*' => Http::response(['error' => 'invalid_client'], 401)]);

        $log = app(SiasnSyncService::class)->syncUnor($instansi);

        $this->assertSame('gagal', $log->status);
        $this->assertStringContainsString('token APIM', $log->pesan[0]);
    }

    public function test_halaman_dan_tombol_sinkron_menjalankan_job(): void
    {
        $instansi = $this->instansi();
        $instansi->update(['siasn_instansi_id' => 'INS-1']);
        $this->fakeSiasn();
        $operator = $this->user(Role::OperatorInstansi, $instansi);

        $this->actingAs($operator)->get('/siasn')->assertOk();
        $this->post('/siasn/sync', ['instansi_id' => $instansi->id, 'jenis' => 'unor'])->assertSessionHas('success');

        // antrian "sync" pada pengujian -> job langsung dijalankan
        $this->assertSame('selesai', SiasnSyncLog::firstOrFail()->status);

        $lain = $this->instansi('LAIN');
        $this->post('/siasn/sync', ['instansi_id' => $lain->id, 'jenis' => 'unor'])->assertForbidden();
    }

    public function test_tanpa_konfigurasi_sinkron_ditolak(): void
    {
        config(['siasn.enabled' => false]);
        $instansi = $this->instansi();

        $this->actingAs($this->user(Role::Admin))
            ->post('/siasn/sync', ['instansi_id' => $instansi->id, 'jenis' => 'pegawai'])
            ->assertSessionHas('error');
        $this->assertSame(0, SiasnSyncLog::count());
    }
}
