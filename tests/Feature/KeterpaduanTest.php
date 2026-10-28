<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UsulanStatus;
use App\Models\AiRiwayat;
use App\Models\KebutuhanSementara;
use App\Models\Pegawai;
use App\Models\Usulan;
use App\Services\MonitoringService;
use App\Services\ProyeksiService;
use App\Services\SaranUsulanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

/**
 * Keterpaduan antarfungsi: kebutuhan sementara, satu rumus saran usulan, pemeriksaan rincian,
 * pengisian formasi dari data pegawai. Tidak ada fungsi yang terblokir karena data hulu belum ada.
 */
class KeterpaduanTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    private function usulan($instansi, UsulanStatus $status = UsulanStatus::Draft, int $tahun = 2027): Usulan
    {
        return Usulan::create(['instansi_id' => $instansi->id, 'tahun' => $tahun, 'perihal' => 'Usulan', 'status' => $status]);
    }

    public function test_kebutuhan_sementara_dipakai_selama_belum_ada_abk_final(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('J');
        $this->pegawai($unit, $jab, 2);
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $monitoring = app(MonitoringService::class);

        // tanpa ABK & tanpa kebutuhan sementara: tetap tampil sebagai "belum ada ABK"
        $this->assertSame(1, $monitoring->summary(['instansi_id' => $instansi->id])['tanpa_abk']);

        $this->actingAs($operator)->post('/kebutuhan-sementara', ['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah' => 5, 'dasar' => 'Peta jabatan 2024'])
            ->assertSessionHas('success');
        $s = $monitoring->summary(['instansi_id' => $instansi->id]);
        $this->assertSame([5, 3, 0, 1], [$s['kebutuhan'], $s['kurang'], $s['tanpa_abk'], $s['posisi_sementara']]);
        $this->assertSame('sementara', $monitoring->positions(['instansi_id' => $instansi->id])->first()['sumber']);

        // ABK draft tidak menggantikan; ABK final menggantikan kebutuhan sementara
        $draft = $this->abk($unit, $jab, 9, 'draft');
        $this->assertSame(5, $monitoring->summary(['instansi_id' => $instansi->id])['kebutuhan']);
        $draft->update(['status' => 'final']);
        $s = $monitoring->summary(['instansi_id' => $instansi->id]);
        $this->assertSame([9, 0], [$s['kebutuhan'], $s['posisi_sementara']]);

        // jumlah 0 menghapus
        $this->post('/kebutuhan-sementara', ['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah' => 0]);
        $this->assertSame(0, KebutuhanSementara::count());
    }

    public function test_kebutuhan_sementara_dibatasi_instansi_operator(): void
    {
        $lain = $this->instansi('B');
        $unitLain = $this->unit($lain, 'UB');
        $operator = $this->user(Role::OperatorInstansi, $this->instansi('A'));

        $this->actingAs($operator)->post('/kebutuhan-sementara', ['unit_kerja_id' => $unitLain->id, 'jabatan_id' => $this->jabatan('J')->id, 'jumlah' => 3])
            ->assertForbidden();
        $this->actingAs($this->user(Role::Pimpinan))->post('/kebutuhan-sementara', [])->assertForbidden();
    }

    public function test_tarik_usulan_tidak_menghitung_ganda(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $gemuk = $this->unit($instansi, 'Gemuk');
        $jab = $this->jabatan('J');
        $jabSementara = $this->jabatan('S');
        $this->abk($unit, $jab, 10);
        $this->pegawai($unit, $jab, 2);
        $this->abk($gemuk, $jab, 1);
        $this->pegawai($gemuk, $jab, 3); // 2 dapat dipindahkan
        KebutuhanSementara::create(['instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $jabSementara->id, 'jumlah' => 4]);

        // formasi ditetapkan belum terisi 3, usulan lain sedang diproses 1
        $tetap = $this->usulan($instansi, UsulanStatus::Ditetapkan, 2026);
        $tetap->details()->create(['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah_usul' => 3, 'jumlah_rekomendasi' => 3, 'jumlah_ditetapkan' => 3]);
        $proses = $this->usulan($instansi, UsulanStatus::VerifikasiBkn);
        $proses->details()->create(['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah_usul' => 1]);

        $usulan = $this->usulan($instansi);
        $this->actingAs($this->user(Role::OperatorInstansi, $instansi));

        // kekurangan 8 − formasi 3 − proses 1 − redistribusi 2 = 2
        $this->post("/usulan/{$usulan->id}/tarik-abk", ['redistribusi' => true])->assertSessionHas('success');
        $d = $usulan->details()->where('jabatan_id', $jab->id)->first();
        $this->assertSame([2, 3, 1, 2, 'abk'], [$d->jumlah_usul, $d->formasi_belum_terisi, $d->usulan_proses, $d->redistribusi_masuk, $d->sumber_kebutuhan]);
        $this->assertStringContainsString('Gemuk', $d->keterangan);

        // posisi tanpa ABK ikut ditarik dari kebutuhan sementara
        $s = $usulan->details()->where('jabatan_id', $jabSementara->id)->first();
        $this->assertSame([4, 'sementara'], [$s->jumlah_usul, $s->sumber_kebutuhan]);

        // tanpa redistribusi
        $this->post("/usulan/{$usulan->id}/tarik-abk", ['redistribusi' => false]);
        $this->assertSame(4, $d->fresh()->jumlah_usul);

        // redistribusi opsional: usulan tanpa redistribusi tidak ditandai melebihi
        $periksa = app(SaranUsulanService::class)->periksa($usulan->fresh());
        $this->assertSame(0, $periksa['jumlah_melebihi']);
        $this->assertSame([4, 2], [$periksa['rincian'][$d->id]['saran'], $periksa['rincian'][$d->id]['saran_redistribusi']]);
    }

    public function test_tarik_memakai_abk_yang_berlaku_untuk_tahun_usulan(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('J');
        $this->abk($unit, $jab, 6)->update(['tahun' => 2028]);
        $this->abk($unit, $jab, 3); // ABK 2026

        $saran = app(SaranUsulanService::class);
        $this->assertSame(3, $saran->hitung($instansi->id, 2027)->first()['saran']);
        $this->assertSame(6, $saran->hitung($instansi->id, 2028)->first()['saran']);
    }

    public function test_pemeriksaan_rincian_hanya_menandai_tidak_memblokir(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('J');
        $tanpaDasar = $this->jabatan('T');
        $this->abk($unit, $jab, 2);
        $usulan = $this->usulan($instansi);
        $this->actingAs($this->user(Role::OperatorInstansi, $instansi));

        $this->post("/usulan/{$usulan->id}/details", ['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah_usul' => 5])
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'melebihi saran sistem (2)'));
        $this->post("/usulan/{$usulan->id}/details", ['unit_kerja_id' => $unit->id, 'jabatan_id' => $tanpaDasar->id, 'jumlah_usul' => 1])
            ->assertSessionHas('warning', fn ($m) => str_contains($m, 'belum memiliki ABK final'));
        $this->assertSame(2, $usulan->details()->count());

        $this->get("/usulan/{$usulan->id}")->assertInertia(fn ($p) => $p
            ->where('pemeriksaan.jumlah_melebihi', 1)
            ->where('pemeriksaan.total_lebih', 3)
            ->where('pemeriksaan.tanpa_dasar', 1)
            ->where('pemeriksaan.kesiapan.total', 1)
            ->where('pemeriksaan.kesiapan.mutakhir', 1));

        // tetap dapat diajukan dan dilihat verifikator
        $this->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'ajukan'])->assertSessionHasNoErrors();
        $this->assertSame(UsulanStatus::Diajukan, $usulan->fresh()->status);
        $this->actingAs($this->user(Role::VerifikatorBkn))->get("/usulan/{$usulan->id}")
            ->assertInertia(fn ($p) => $p->where('pemeriksaan.jumlah_melebihi', 1));
    }

    public function test_pegawai_yang_ditautkan_mengisi_formasi_otomatis(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('J');
        $usulan = $this->usulan($instansi, UsulanStatus::Ditetapkan);
        $detail = $usulan->details()->create(['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah_usul' => 3, 'jumlah_rekomendasi' => 3, 'jumlah_ditetapkan' => 3]);
        $operator = $this->user(Role::OperatorInstansi, $instansi);

        $data = fn ($nip) => ['instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'nip' => $nip,
            'nama' => 'CPNS', 'status_kepegawaian' => 'pns', 'is_active' => true, 'usulan_detail_id' => $detail->id];

        $this->actingAs($operator)->get('/pegawai/create')->assertInertia(fn ($p) => $p->where('formasiOptions.0.id', $detail->id));
        $this->post('/pegawai', $data('199001012020011001'))->assertSessionHasNoErrors();
        $this->post('/pegawai', $data('199001012020011002'))->assertSessionHasNoErrors();
        $this->assertSame(2, $detail->fresh()->jumlah_terisi);

        // jumlah terisi tidak boleh di bawah pegawai yang tertaut
        $this->put('/formasi', ['items' => [['id' => $detail->id, 'jumlah_terisi' => 1]]])->assertSessionHasErrors('items');

        // nonaktif / hapus menurunkan kembali
        $p = Pegawai::where('nip', '199001012020011001')->first();
        $p->update(['is_active' => false]);
        $this->assertSame(1, $detail->fresh()->jumlah_terisi);
        Pegawai::where('nip', '199001012020011002')->first()->delete();
        $this->assertSame(0, $detail->fresh()->jumlah_terisi);

        // pengisian manual tanpa tautan tetap bisa
        $this->put('/formasi', ['items' => [['id' => $detail->id, 'jumlah_terisi' => 2]]])->assertSessionHasNoErrors();
        $this->assertSame(2, $detail->fresh()->jumlah_terisi);

        // formasi instansi lain / belum ditetapkan ditolak
        $draft = $this->usulan($instansi)->details()->create(['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah_usul' => 1]);
        $this->post('/pegawai', ['usulan_detail_id' => $draft->id] + $data('199001012020011003'))->assertSessionHasErrors('usulan_detail_id');
    }

    public function test_proyeksi_menyertakan_kebutuhan_sementara(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('J');
        $this->pegawai($unit, $jab, 1);
        KebutuhanSementara::create(['instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah' => 3]);

        $hasil = app(ProyeksiService::class)->hitung($instansi->id);
        $this->assertCount(1, $hasil['rows']);
        $this->assertSame('sementara', $hasil['rows'][0]['sumber']);
        $this->assertSame(2, $hasil['total'][$hasil['tahun'][0]]['kekurangan']);

        $this->actingAs($this->user(Role::Admin))->get("/proyeksi?instansi_id={$instansi->id}")->assertOk();
        $this->get("/monitoring/unit/{$unit->id}")->assertInertia(fn ($p) => $p->where('positions.0.sumber', 'sementara')->has('sementara'));
        $this->get("/monitoring/instansi/{$instansi->id}")->assertInertia(fn ($p) => $p->where('kesiapan.sementara', 1));
    }

    public function test_justifikasi_ai_usulan_tersimpan_di_riwayat(): void
    {
        config(['gemini.keys' => ['k1'], 'gemini.enabled' => true]);
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Justifikasi **usulan**']]]]]])]);

        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('J');
        $this->abk($unit, $jab, 2);
        $usulan = $this->usulan($instansi);
        $usulan->details()->create(['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah_usul' => 2]);

        $this->actingAs($this->user(Role::OperatorInstansi, $instansi));
        $this->postJson("/ai/usulan/{$usulan->id}")->assertOk()->assertJsonPath('teks', 'Justifikasi **usulan**');
        $this->postJson("/ai/usulan/{$usulan->id}")->assertJsonPath('riwayat.dari_riwayat', true);
        Http::assertSentCount(1);
        $this->assertSame(1, AiRiwayat::where('jenis', 'justifikasi_usulan')->count());

        $this->actingAs($this->user(Role::OperatorInstansi, $this->instansi('B')))->postJson("/ai/usulan/{$usulan->id}")->assertForbidden();
    }
}
