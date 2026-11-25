<?php

namespace Tests\Feature;

use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\JfAsn;
use App\Models\JfAsnSyncItem;
use App\Models\JfAsnSyncRun;
use App\Models\RefInstansiBkn;
use App\Services\JfPembina\JfAsnSyncService;
use App\Services\JfPembina\JfPembinaClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Sinkronisasi data JF Pembina BKN. API asli sedang tidak dapat diakses, sehingga seluruhnya memakai Http::fake. */
class JfPembinaSyncTest extends TestCase
{
    use RefreshDatabase;

    private const JABATAN_MUDA = 'Analis Sumber Daya Manusia Aparatur Ahli Muda';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jfpembina.enabled' => true,
            'jfpembina.base_url' => 'https://jf.test/pusbin/1',
            'jfpembina.apim.url' => 'https://jf.test/oauth2/token',
            'jfpembina.apim.username' => 'client',
            'jfpembina.apim.password' => 'secret',
            'jfpembina.sso.access_token' => 'SSO-TOKEN',
            'jfpembina.halaman' => 2,
            'jfpembina.chunk' => 2,
        ]);
    }

    private function ref(string $siasnId = 'INS-1', string $nama = 'Kementerian Contoh'): RefInstansiBkn
    {
        return RefInstansiBkn::create(['siasn_id' => $siasnId, 'nama' => $nama, 'jenis' => 'P', 'kode' => '1', 'jenis_instansi' => 'LPNK']);
    }

    /**
     * @param  array<int, array{id: string, nip: string, nama: string}>  $daftar
     * @param  array<string, array<string, mixed>>  $detail  nip => data-utama
     */
    private function fakeBkn(array $daftar, array $detail = []): void
    {
        Http::fake([
            'jf.test/oauth2/token' => Http::response(['access_token' => 'APIM-TOKEN']),
            'jf.test/pusbin/1/pns/list-asn-pembinajf*' => function (Request $request) use ($daftar) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);

                return Http::response(['count' => count($daftar), 'data' => array_slice($daftar, (int) $q['offset'], (int) $q['limit'])]);
            },
            'jf.test/pusbin/1/pns/data-utama/*' => function (Request $request) use ($detail) {
                $nip = basename(parse_url($request->url(), PHP_URL_PATH));

                return isset($detail[$nip]) ? Http::response(['data' => $detail[$nip]]) : Http::response(['data' => null], 404);
            },
        ]);
    }

    private function dataUtama(string $nama = self::JABATAN_MUDA, string $fungsionalId = 'JF-1'): array
    {
        return [
            'jabatanFungsionalId' => $fungsionalId, 'jabatanFungsionalNama' => $nama, 'unorNama' => 'Biro SDM',
            'golRuangAkhir' => 'III/c', 'tmtJabatan' => '15-03-2022', 'kedudukanPnsNama' => 'Aktif', 'instansiKerjaId' => 'INS-1',
        ];
    }

    private function asn(string $nip, string $nama = 'Pegawai'): array
    {
        return ['id' => 'ID-'.$nip, 'nip' => $nip, 'nama' => $nama.' '.$nip];
    }

    public function test_sinkron_mengambil_daftar_berhalaman_lalu_detail_dan_memetakan_jabatan(): void
    {
        $ref = $this->ref();
        $jabatan = Jabatan::create(['kode' => 'JF-ASDM-M', 'nama' => self::JABATAN_MUDA, 'jenis' => 'fungsional']);
        $this->fakeBkn(
            [$this->asn('1001'), $this->asn('1002'), $this->asn('1003')], // 3 ASN, halaman 2 => 2 halaman
            ['1001' => $this->dataUtama(), '1002' => $this->dataUtama(), '1003' => $this->dataUtama('Pranata Sumber Daya Manusia Aparatur Terampil', 'JF-2')],
        );

        $run = app(JfAsnSyncService::class)->mulai();
        $run->refresh();

        $this->assertSame('done', $run->status);
        $this->assertSame(3, $run->asn_new);
        $this->assertSame(3, $run->detail_fetched);
        $this->assertSame(0, $run->detail_failed);
        $this->assertSame(3, JfAsn::where('ref_instansi_id', $ref->id)->count());

        $a = JfAsn::where('nip', '1001')->first();
        $this->assertSame('Analis Sumber Daya Manusia Aparatur', $a->jabatan_pokok);
        $this->assertSame('Ahli Muda', $a->jenjang);
        $this->assertSame('III/c', $a->gol_ruang);
        $this->assertSame('2022-03-15', $a->tmt_jabatan->toDateString());
        $this->assertSame($jabatan->id, $a->jabatan_id);
        $this->assertNull(JfAsn::where('nip', '1003')->value('jabatan_id'), 'jabatan yang tidak ada di master dibiarkan kosong');
        $this->assertSame(3, $ref->fresh()->jf_asn_total);
        $this->assertNotNull($ref->fresh()->jf_synced_at);
    }

    public function test_asn_yang_hilang_dinonaktifkan_tetapi_baris_manual_tidak_disentuh(): void
    {
        $ref = $this->ref();
        $lama = ['ref_instansi_id' => $ref->id, 'nama' => 'Lama', 'last_seen_at' => now()->subWeek()];
        JfAsn::create($lama + ['nip' => '2001', 'source' => 'bkn']);
        JfAsn::create($lama + ['nip' => '2002', 'source' => 'manual']);
        $this->fakeBkn([$this->asn('2003')], ['2003' => $this->dataUtama()]);

        $run = app(JfAsnSyncService::class)->mulai()->fresh();

        $this->assertSame(1, $run->asn_deactivated);
        $this->assertFalse(JfAsn::where('nip', '2001')->value('is_active'));
        $this->assertTrue(JfAsn::where('nip', '2002')->value('is_active'));
        $this->assertTrue(JfAsn::where('nip', '2003')->value('is_active'));
    }

    public function test_gagal_mengambil_daftar_tidak_menonaktifkan_siapa_pun(): void
    {
        $ref = $this->ref();
        JfAsn::create(['ref_instansi_id' => $ref->id, 'nama' => 'Ada', 'nip' => '3001', 'source' => 'bkn', 'last_seen_at' => now()->subWeek()]);
        Http::fake([
            'jf.test/oauth2/token' => Http::response(['access_token' => 'APIM-TOKEN']),
            'jf.test/pusbin/1/pns/list-asn-pembinajf*' => Http::response('gateway timeout', 504),
        ]);

        $run = app(JfAsnSyncService::class)->mulai()->fresh();

        $this->assertSame('failed', $run->status);
        $this->assertSame(1, $run->instansi_done);
        $this->assertSame([$ref->siasn_id], app(JfAsnSyncService::class)->instansiGagal($run));
        $this->assertTrue(JfAsn::where('nip', '3001')->value('is_active'), 'daftar gagal: ASN lama tidak boleh dinonaktifkan');
    }

    public function test_detail_yang_gagal_dihitung_percobaannya_dan_tidak_menggagalkan_instansi(): void
    {
        config(['jfpembina.concurrency' => 1]);
        $this->ref();
        // 3002 tidak punya data-utama (404) sehingga tetap gagal setelah semua ronde
        $this->fakeBkn([$this->asn('4001'), $this->asn('4002')], ['4001' => $this->dataUtama()]);

        // detailBanyak() menunggu 2+4 dtk antar-ronde; tidak relevan untuk kebenaran, jadi cukup satu ronde
        $client = new class extends JfPembinaClient
        {
            public function detailBanyak(array $nips, ?int $konkurensi = null, int $ronde = 3): array
            {
                return parent::detailBanyak($nips, $konkurensi, 1);
            }
        };
        $this->app->instance(JfPembinaClient::class, $client);

        $run = app(JfAsnSyncService::class)->mulai()->fresh();

        $this->assertSame('done', $run->status);
        $this->assertSame(1, $run->detail_fetched);
        $this->assertSame(1, $run->detail_failed);
        $this->assertNotNull(JfAsn::where('nip', '4001')->value('detail_synced_at'));
        $this->assertNull(JfAsn::where('nip', '4002')->value('detail_synced_at'));
        $this->assertSame(1, (int) JfAsn::where('nip', '4002')->value('detail_attempts'));
    }

    public function test_detail_yang_masih_segar_tidak_diambil_ulang(): void
    {
        $ref = $this->ref();
        JfAsn::create(['ref_instansi_id' => $ref->id, 'nama' => 'Segar', 'nip' => '5001', 'source' => 'bkn', 'detail_synced_at' => now()->subDay(), 'jabatan_fungsional_nama' => 'X']);
        $this->fakeBkn([$this->asn('5001')]);

        $run = app(JfAsnSyncService::class)->mulai()->fresh();

        $this->assertSame(0, $run->detail_fetched);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/pns/data-utama/'));
    }

    public function test_putaran_macet_dapat_dilanjutkan_dan_item_selesai_tidak_diulang(): void
    {
        $a = $this->ref('INS-1', 'Instansi A');
        $b = $this->ref('INS-2', 'Instansi B');
        $service = app(JfAsnSyncService::class);

        $run = JfAsnSyncRun::create(['status' => 'running', 'with_detail' => false, 'instansi_total' => 2, 'started_at' => now()->subHour()]);
        JfAsnSyncItem::create(['run_id' => $run->id, 'ref_instansi_id' => $a->id, 'status' => 'done']);
        JfAsnSyncItem::create(['run_id' => $run->id, 'ref_instansi_id' => $b->id, 'status' => 'listing']);
        JfAsnSyncRun::whereKey($run->id)->update(['updated_at' => now()->subHour()]);

        $this->assertSame($run->id, $service->putaranMacet()?->id);
        $this->assertFalse($service->sedangBerjalan());

        $this->fakeBkn([$this->asn('6001')]);
        $this->assertSame(1, $service->lanjutkan($run->fresh()));

        $this->assertSame('done', $run->fresh()->status);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'instansi=INS-2'));
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'instansi=INS-1'));
    }

    public function test_pemetaan_instansi_lewat_id_siasn_lalu_nama(): void
    {
        $lewatId = Instansi::create(['kode' => 'A', 'nama' => 'Nama Lain Sama Sekali', 'siasn_instansi_id' => 'INS-1']);
        $lewatNama = Instansi::create(['kode' => 'B', 'nama' => 'Kabupaten Bantul']);
        $a = $this->ref('INS-1', 'Pemerintah Daerah X');
        $b = $this->ref('INS-2', 'Kab. Bantul');
        $c = $this->ref('INS-3', 'Tidak Dikenal');
        JfAsn::create(['ref_instansi_id' => $b->id, 'nama' => 'P', 'nip' => '7001']);

        $hasil = app(JfAsnSyncService::class)->petakanInstansi();

        $this->assertSame(['terpetakan' => 2, 'belum' => 1], $hasil);
        $this->assertSame($lewatId->id, $a->fresh()->instansi_id);
        $this->assertSame($lewatNama->id, $b->fresh()->instansi_id);
        $this->assertNull($c->fresh()->instansi_id);
        $this->assertSame($lewatNama->id, JfAsn::where('nip', '7001')->value('instansi_id'), 'dipropagasi ke baris ASN');
    }

    public function test_pemetaan_jabatan_lewat_id_siasn_lalu_nama(): void
    {
        $ref = $this->ref();
        $lewatId = Jabatan::create(['kode' => 'J1', 'nama' => 'Nama Internal', 'jenis' => 'fungsional', 'siasn_jabatan_id' => 'JF-9']);
        $lewatNama = Jabatan::create(['kode' => 'J2', 'nama' => 'Pranata Sumber Daya Manusia Aparatur Mahir', 'jenis' => 'fungsional']);
        $base = ['ref_instansi_id' => $ref->id, 'nama' => 'P'];
        JfAsn::create($base + ['nip' => '8001', 'jabatan_fungsional_id' => 'JF-9', 'jabatan_fungsional_nama' => 'Nama Di BKN']);
        JfAsn::create($base + ['nip' => '8002', 'jabatan_fungsional_id' => 'JF-5', 'jabatan_fungsional_nama' => 'pranata sumber daya manusia aparatur mahir']);
        JfAsn::create($base + ['nip' => '8003', 'jabatan_fungsional_id' => 'JF-7', 'jabatan_fungsional_nama' => 'Jabatan Asing']);

        $hasil = app(JfAsnSyncService::class)->petakanJabatan();

        $this->assertSame(['terpetakan' => 2, 'belum' => 1], $hasil);
        $this->assertSame($lewatId->id, JfAsn::where('nip', '8001')->value('jabatan_id'));
        $this->assertSame($lewatNama->id, JfAsn::where('nip', '8002')->value('jabatan_id'));
        $this->assertNull(JfAsn::where('nip', '8003')->value('jabatan_id'));
    }

    public function test_pisah_jabatan_mengenali_jenjang(): void
    {
        $this->assertSame(['Analis Sumber Daya Manusia Aparatur', 'Ahli Madya'], JfAsn::pisahJabatan('Analis Sumber Daya Manusia Aparatur Ahli Madya'));
        $this->assertSame(['Pranata Sumber Daya Manusia Aparatur', 'Terampil'], JfAsn::pisahJabatan('Pranata Sumber Daya Manusia Aparatur terampil'));
        $this->assertSame(['Jabatan Tanpa Jenjang', null], JfAsn::pisahJabatan('Jabatan Tanpa Jenjang'));
        $this->assertSame(['', null], JfAsn::pisahJabatan(null));
    }

    public function test_klien_membutuhkan_konfigurasi_dan_token_apim_di_cache(): void
    {
        config(['jfpembina.enabled' => false]);
        $this->assertFalse(app(JfPembinaClient::class)->isConfigured());

        config(['jfpembina.enabled' => true]);
        $this->fakeBkn([$this->asn('9001')]);
        $client = app(JfPembinaClient::class);
        $this->assertTrue($client->isConfigured());

        $client->daftarAsn('INS-1');
        $client->daftarAsn('INS-1');

        Http::assertSentCount(3); // satu token APIM + dua daftar (token di-cache)
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'list-asn-pembinajf')
            && $r->hasHeader('Authorization', 'Bearer APIM-TOKEN') && $r->hasHeader('Auth', 'Bearer SSO-TOKEN'));
    }
}
