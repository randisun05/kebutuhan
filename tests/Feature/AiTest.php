<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AiRiwayat;
use App\Models\Peringatan;
use App\Services\Ai\AiException;
use App\Services\Ai\GeminiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class AiTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gemini.keys' => ['key-satu', 'key-dua', 'key-tiga'], 'gemini.enabled' => true]);
    }

    private function ok(string $teks): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $teks]]], 'finishReason' => 'STOP']]];
    }

    private function batas(bool $harian = false): array
    {
        return ['error' => ['code' => 429, 'status' => 'RESOURCE_EXHAUSTED', 'message' => 'Quota exceeded', 'details' => [
            ['@type' => 'type.googleapis.com/google.rpc.QuotaFailure', 'violations' => [
                ['quotaId' => $harian ? 'GenerateRequestsPerDayPerProjectPerModel-FreeTier' : 'GenerateRequestsPerMinutePerProjectPerModel-FreeTier'],
            ]],
            ['@type' => 'type.googleapis.com/google.rpc.RetryInfo', 'retryDelay' => '30s'],
        ]]];
    }

    private function keyDipakai(): array
    {
        return Http::recorded()->map(fn ($r) => $r[0]->header('x-goog-api-key')[0])->all();
    }

    public function test_key_dipakai_bergiliran(): void
    {
        Http::fake(['*' => Http::response($this->ok('halo'))]);
        $gemini = app(GeminiClient::class);

        foreach (range(1, 4) as $_) {
            $this->assertSame('halo', $gemini->generate('tes'));
        }

        $this->assertSame(['key-satu', 'key-dua', 'key-tiga', 'key-satu'], $this->keyDipakai());
    }

    public function test_key_yang_habis_kuota_dilewati_dan_dipakai_lagi_setelah_pulih(): void
    {
        Http::fake(function (Request $r) {
            return match ($r->header('x-goog-api-key')[0]) {
                'key-satu' => Http::response($this->batas(harian: true), 429),
                'key-dua' => Http::response($this->batas(), 429),
                default => Http::response($this->ok('dari key tiga')),
            };
        });
        $gemini = app(GeminiClient::class);

        $this->assertSame('dari key tiga', $gemini->generate('tes'));
        $this->assertSame(['key-satu', 'key-dua', 'key-tiga'], $this->keyDipakai());

        $status = collect($gemini->status())->keyBy('nomor');
        $this->assertFalse($status[1]['tersedia']);
        $this->assertSame('Kuota harian habis', $status[1]['alasan']);
        $this->assertFalse($status[2]['tersedia']);
        $this->assertTrue($status[3]['tersedia']);
        $this->assertSame(1, $status[3]['pemakaian_hari_ini']);

        // permintaan berikutnya langsung ke key yang masih tersedia
        $this->assertSame('dari key tiga', $gemini->generate('tes lagi'));
        $this->assertSame(['key-satu', 'key-dua', 'key-tiga', 'key-tiga'], $this->keyDipakai());

        // rate limit per menit pulih setelah retryDelay, key dua ikut bergiliran lagi
        $this->travel(31)->seconds();
        $this->assertTrue(collect($gemini->status())->firstWhere('nomor', 2)['tersedia']);
        $this->assertFalse(collect($gemini->status())->firstWhere('nomor', 1)['tersedia']);
    }

    public function test_semua_key_habis_memberi_pesan_jelas(): void
    {
        Http::fake(['*' => Http::response($this->batas(harian: true), 429)]);
        $gemini = app(GeminiClient::class);

        try {
            $gemini->generate('tes');
            $this->fail('Seharusnya gagal');
        } catch (AiException $e) {
            $this->assertStringContainsString('Batas pemakaian', $e->getMessage());
        }

        // semua key sedang istirahat: tidak ada permintaan baru ke Google
        $this->expectExceptionMessage('Semua API key AI sedang mencapai batas');
        $gemini->generate('tes');
    }

    public function test_key_ditolak_diistirahatkan_dan_galat_permintaan_tidak_diulang(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['code' => 400, 'message' => 'API key not valid. Please pass a valid API key.', 'details' => [['reason' => 'API_KEY_INVALID']]]], 400)
            ->push($this->ok('oke'))
            ->push(['error' => ['code' => 400, 'message' => 'Invalid JSON payload']], 400);
        $gemini = app(GeminiClient::class);

        $this->assertSame('oke', $gemini->generate('tes'));
        $this->assertFalse($gemini->status()[0]['tersedia']);

        $this->expectExceptionMessage('Permintaan ke layanan AI ditolak');
        $gemini->generate('tes');
    }

    public function test_tanpa_key_fitur_nonaktif(): void
    {
        config(['gemini.keys' => []]);
        $this->assertFalse(app(GeminiClient::class)->aktif());

        $instansi = $this->instansi();
        $this->actingAs($this->user(Role::Admin))
            ->postJson("/ai/monitoring/{$instansi->id}")
            ->assertStatus(503)->assertJsonPath('message', fn ($m) => str_contains($m, 'GEMINI_API_KEY'));
    }

    public function test_draft_anjab_dibersihkan_sesuai_aturan_form(): void
    {
        Http::fake(['*' => Http::response($this->ok(json_encode([
            'ikhtisar_jabatan' => 'Melakukan analisis kepegawaian.',
            'uraian_tugas' => [
                ['uraian_tugas' => 'Menyusun rencana kebutuhan pegawai', 'hasil_kerja' => 'Dokumen', 'volume' => 12, 'satuan_periode' => 'bulan', 'norma_waktu' => 300],
                ['uraian_tugas' => 'Memverifikasi data', 'hasil_kerja' => 'Berkas', 'volume' => -5, 'satuan_periode' => 'dekade', 'norma_waktu' => 999999],
                ['uraian_tugas' => '  '],
            ],
            'informasi' => ['bahan_kerja' => ['Data pegawai', ''], 'prestasi_diharapkan' => 'Dokumen tepat waktu.'],
        ])))]);

        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'Bagian Kepegawaian');
        $jabatan = $this->jabatan('ANL');

        $res = $this->actingAs($this->user(Role::OperatorInstansi, $instansi))
            ->postJson('/ai/anjab-draft', ['jabatan_id' => $jabatan->id, 'unit_kerja_id' => $unit->id])
            ->assertOk()
            ->assertJsonCount(2, 'uraian')
            ->json();

        $this->assertSame(['uraian_tugas' => 'Menyusun rencana kebutuhan pegawai', 'hasil_kerja' => 'Dokumen', 'volume' => 12, 'satuan_periode' => 'bulan', 'norma_waktu' => 300], $res['uraian'][0]);
        $this->assertSame(0, $res['uraian'][1]['volume']);
        $this->assertSame('tahun', $res['uraian'][1]['satuan_periode']);
        $this->assertSame(75000, $res['uraian'][1]['norma_waktu']);
        $this->assertSame(['Data pegawai'], $res['informasi']['bahan_kerja']);
        $this->assertSame([], $res['informasi']['wewenang']);

        Http::assertSent(fn (Request $r) => str_contains($r['contents'][0]['parts'][0]['text'], 'Bagian Kepegawaian')
            && $r['generationConfig']['responseMimeType'] === 'application/json');
    }

    public function test_akses_fitur_ai_dibatasi_instansi_dan_peran(): void
    {
        Http::fake(['*' => Http::response($this->ok('## Ringkasan kondisi'."\n".'Aman. <script>alert(1)</script>'))]);

        $instansi = $this->instansi('A');
        $lain = $this->instansi('B');
        $unitLain = $this->unit($lain, 'Unit B');
        $jabatan = $this->jabatan('X');
        $operator = $this->user(Role::OperatorInstansi, $instansi);

        $this->actingAs($operator)->postJson("/ai/monitoring/{$lain->id}")->assertForbidden();
        $this->actingAs($operator)->postJson('/ai/anjab-draft', ['jabatan_id' => $jabatan->id, 'unit_kerja_id' => $unitLain->id])->assertForbidden();
        $this->actingAs($this->user(Role::Pimpinan))->postJson('/ai/anjab-draft', ['jabatan_id' => $jabatan->id])->assertForbidden();

        $peringatan = Peringatan::create(['kode' => 'jabatan_kosong', 'tingkat' => 'tinggi', 'instansi_id' => $lain->id, 'kunci' => 'x',
            'judul' => 'Jabatan kosong', 'pesan' => 'Kosong', 'status' => 'aktif', 'pertama_terdeteksi_at' => now(), 'terakhir_terdeteksi_at' => now()]);
        $this->actingAs($operator)->postJson("/ai/peringatan/{$peringatan->id}")->assertForbidden();

        $html = $this->actingAs($operator)->postJson("/ai/monitoring/{$instansi->id}")->assertOk()->json('html');
        $this->assertStringContainsString('<h2>Ringkasan kondisi</h2>', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $this->actingAs($this->user(Role::Admin))->postJson("/ai/peringatan/{$peringatan->id}")->assertOk()->assertJsonStructure(['html']);
    }

    public function test_hasil_disimpan_di_riwayat_dan_tidak_digenerate_ulang(): void
    {
        Http::fakeSequence()->push($this->ok('Analisis pertama'))->push($this->ok('Analisis kedua'))->push($this->ok('Analisis fungsional'));

        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'Bagian Umum');
        $jabatan = $this->jabatan('PNT');
        $this->abk($unit, $jabatan, 3);
        $admin = $this->user(Role::Admin);

        $r1 = $this->actingAs($admin)->postJson("/ai/monitoring/{$instansi->id}")->assertOk()->json();
        $this->assertStringContainsString('Analisis pertama', $r1['html']);
        $this->assertFalse($r1['riwayat']['dari_riwayat']);

        // permintaan kedua memakai riwayat, Gemini tidak dipanggil lagi
        $r2 = $this->postJson("/ai/monitoring/{$instansi->id}")->assertOk()->json();
        $this->assertStringContainsString('Analisis pertama', $r2['html']);
        $this->assertTrue($r2['riwayat']['dari_riwayat']);
        $this->assertFalse($r2['riwayat']['usang']);
        Http::assertSentCount(1);

        // analisis tersimpan langsung tampil di halaman monitoring
        $this->get("/monitoring/instansi/{$instansi->id}")->assertInertia(fn ($p) => $p
            ->where('analisisAi.riwayat.id', $r1['riwayat']['id']));

        // data berubah: hasil lama tetap dipakai tetapi ditandai usang
        $this->pegawai($unit, $jabatan, 2);
        $this->assertTrue($this->postJson("/ai/monitoring/{$instansi->id}")->json('riwayat.usang'));
        Http::assertSentCount(1);

        // "Buat ulang" memanggil AI dan menambah baris riwayat
        $r3 = $this->postJson("/ai/monitoring/{$instansi->id}", ['baru' => true])->assertOk()->json();
        $this->assertStringContainsString('Analisis kedua', $r3['html']);
        Http::assertSentCount(2);

        $this->assertSame(2, AiRiwayat::where('jenis', 'analisis_monitoring')->count());
        $this->assertSame($admin->id, AiRiwayat::latest('id')->first()->user_id);

        // filter berbeda = subjek berbeda
        $this->postJson("/ai/monitoring/{$instansi->id}", ['jenis' => 'fungsional'])->assertJsonPath('riwayat.dari_riwayat', false);
    }

    public function test_jawaban_json_rusak_tidak_disimpan(): void
    {
        Http::fake(['*' => Http::response($this->ok('bukan json'))]);
        $jabatan = $this->jabatan('J');

        $this->actingAs($this->user(Role::Admin))->postJson('/ai/anjab-draft', ['jabatan_id' => $jabatan->id])->assertStatus(503);
        $this->assertSame(0, AiRiwayat::count());
    }
}
