<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ImporProfil;
use App\Models\InstansiAlias;
use App\Models\Jabatan;
use App\Models\JfJabatanPokok;
use App\Models\JfJenjang;
use App\Models\KebutuhanFakta;
use App\Models\RefInstansiBkn;
use App\Models\RekomendasiJf;
use App\Models\SumberData;
use App\Services\RekomendasiJfImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\Feature\Concerns\BuildsRekomendasiExcel;
use Tests\TestCase;

/** Impor Excel rekap kebutuhan JF MASN (rekomendasi BKN dan penetapan KemenPANRB). */
class RekomendasiJfTest extends TestCase
{
    use BuildsKebutuhanData, BuildsRekomendasiExcel, RefreshDatabase;

    public function test_baca_memetakan_jenjang_sumber_surat_dan_instansi(): void
    {
        RefInstansiBkn::create(['siasn_id' => 'A1', 'nama' => 'Pemerintah Kabupaten Gunungkidul', 'jenis_instansi' => 'KAB']);
        $path = $this->buatExcel([
            $this->baris('Pemerintah Kab. Gunungkidul', 'Analis SDM Aparatur', [
                9 => "- 4401/B-BJ.02.01/SD/C/2024\n- 6778/B-BJ.01.01/SD/C/2026", 10 => "- 27 Juni 2024\n- 15 Oktober 2026", 11 => 'https://drive.example/surat',
                15 => '=IF(ISBLANK(K4)=FALSE,MONTH(K4),"")', 21 => '=SUM(R4:U4)',
            ]),
            $this->baris('Pemerintah Kab. Gunungkidul', 'Pranata SDM Aparatur', [17 => 5, 18 => 2, 19 => 0, 20 => 3, 22 => 64, 23 => 54, 24 => 12, 25 => 7]),
            $this->baris('Instansi Antah Berantah', 'Asesor SDM Aparatur', [7 => 'Belum Usul', 17 => null, 18 => null, 19 => null, 20 => null, 22 => null, 23 => null, 24 => null, 25 => null, 37 => null, 38 => null, 39 => null, 41 => null, 42 => null, 43 => null]),
        ]);

        $h = app(RekomendasiJfImportService::class)->baca($path);
        unlink($path);

        $this->assertSame(3, $h['ringkas']['baris']);
        $this->assertSame(['Instansi Antah Berantah'], $h['ringkas']['instansi_tidak_cocok']);
        // Analis: bezetting 2+3+1 = 6, rekom BKN 16+12+9+1 = 38, Menpan 16+10+9 = 35
        // Pranata (3 jenjang; angka slot keempat diabaikan): bezetting 5+2+0 = 7, rekom 64+54+12 = 130, Menpan 35
        $this->assertSame(['bezetting_rekom' => 13, 'rekom_bkn' => 168, 'penetapan_menpan' => 70], $h['ringkas']['angka_per_sumber']);

        $analis = collect($h['baris'])->firstWhere('jabatan_nama', 'Analis SDM Aparatur');
        $this->assertNotNull($analis['ref_instansi_id']); // "Kab." dinormalkan ke "Kabupaten"
        $this->assertSame('POKJA 2', $analis['pokja']);
        $this->assertSame('ditetapkan', $analis['status_menpan']);
        $f = collect($analis['fakta']);
        $this->assertSame(16, $f->first(fn ($x) => $x['sumber'] === 'rekom_bkn' && $x['jenjang'] === 'pertama')['nilai']);
        $this->assertSame(['pertama', 'muda', 'madya', 'utama'], $f->where('sumber', 'rekom_bkn')->pluck('jenjang')->all());

        // dua surat rekomendasi dari satu sel, tanggal teks Indonesia, tautan hanya pada surat pertama
        $rekom = collect($analis['surat'])->where('jenis', 'rekomendasi')->values();
        $this->assertSame(['4401/B-BJ.02.01/SD/C/2024', '6778/B-BJ.01.01/SD/C/2026'], $rekom->pluck('nomor')->all());
        $this->assertSame(['2024-06-27', '2026-10-15'], $rekom->pluck('tanggal')->all());
        $this->assertSame('https://drive.example/surat', $rekom[0]['tautan']);
        $this->assertArrayNotHasKey('tautan', $rekom[1]);
        // tanggal berupa nomor seri Excel
        $this->assertSame('2025-08-31', collect($analis['surat'])->firstWhere('jenis', 'penetapan_menpan')['tanggal']);

        // Pranata: tiga jenjang keterampilan; angka pada slot keempat diabaikan dan diperingatkan
        $pranata = collect($h['baris'])->firstWhere('jabatan_nama', 'Pranata SDM Aparatur');
        $this->assertSame(['terampil', 'mahir', 'penyelia'], collect($pranata['fakta'])->where('sumber', 'rekom_bkn')->pluck('jenjang')->all());
        $this->assertTrue(collect($h['peringatan'])->contains(fn ($p) => str_contains($p, 'tidak punya jenjang itu')));

        // baris tanpa angka tetap tercatat (cakupan "belum usul") tetapi tanpa fakta dan tanpa penetapan
        $belum = collect($h['baris'])->firstWhere('nama_instansi', 'Instansi Antah Berantah');
        $this->assertSame([], $belum['fakta']);
        $this->assertNull($belum['status_menpan']);
        $this->assertNull($belum['ref_instansi_id']);
    }

    public function test_sel_rumus_dibaca_dari_nilai_tersimpan(): void
    {
        // Di Excel sumber banyak kolom isian berisi VLOOKUP dari sheet lain; nilai tersimpannya adalah datanya.
        $path = $this->buatExcel([$this->baris('Instansi Rumus', 'Analis SDM Aparatur', [
            17 => '=SUM(10,6)', 22 => '=40+5', 37 => '=SUM(30,3)', 41 => '="B/9/M.SM.01.00/2025"', 42 => '=DATE(2025,8,31)', 9 => '="111/B/2025"', 10 => '=DATE(2025,6,4)',
            15 => '=#REF!',
        ])]);

        $h = app(RekomendasiJfImportService::class)->baca($path);
        unlink($path);

        $b = $h['baris'][0];
        $nilai = fn ($sumber) => collect($b['fakta'])->first(fn ($x) => $x['sumber'] === $sumber && $x['jenjang'] === 'pertama')['nilai'];
        $this->assertSame([16, 45, 33], [$nilai('bezetting_rekom'), $nilai('rekom_bkn'), $nilai('penetapan_menpan')]);
        $menpan = collect($b['surat'])->firstWhere('jenis', 'penetapan_menpan');
        $this->assertSame(['B/9/M.SM.01.00/2025', '2025-08-31'], [$menpan['nomor'], $menpan['tanggal']]);
        $rekom = collect($b['surat'])->firstWhere('jenis', 'rekomendasi');
        $this->assertSame(['111/B/2025', '2025-06-04'], [$rekom['nomor'], $rekom['tanggal']]);
    }

    public function test_sheet_tidak_ada_ditolak_dengan_pesan_jelas(): void
    {
        $path = $this->buatExcel([$this->baris('X', 'Analis SDM Aparatur')], 'Sheet Lain');

        $this->expectExceptionMessage('Sheet "'.ImporProfil::first()->sheet.'" tidak ditemukan');
        app(RekomendasiJfImportService::class)->baca($path);
    }

    public function test_jabatan_tak_dikenal_dilewati_dengan_peringatan(): void
    {
        $path = $this->buatExcel([$this->baris('Instansi A', 'Pilot Helikopter'), $this->baris('Instansi A', 'Analis SDM Aparatur')]);
        $h = app(RekomendasiJfImportService::class)->baca($path);
        unlink($path);

        $this->assertCount(1, $h['baris']);
        $this->assertTrue(collect($h['peringatan'])->contains(fn ($p) => str_contains($p, 'Pilot Helikopter') && str_contains($p, 'master Jabatan Pokok')));
    }

    public function test_simpan_idempoten_berversi_dan_sel_kosong_tidak_menghapus(): void
    {
        $svc = app(RekomendasiJfImportService::class);
        $path = $this->buatExcel([$this->baris('Instansi A', 'Analis SDM Aparatur', [9 => '111/B', 10 => 45700])]);

        $pertama = $svc->simpan($svc->baca($path), null, 'rekap.xlsx');
        $this->assertSame([1, 0], [$pertama->dibuat, $pertama->diperbarui]);
        $rekom = RekomendasiJf::firstOrFail();
        $this->assertSame(16, $this->fakta($rekom, 'rekom_bkn', 'pertama'));
        $faktaAwal = KebutuhanFakta::count();

        // impor ulang tanpa perubahan: tidak ada fakta baru, tidak ada header ganda
        $ulang = $svc->simpan($svc->baca($path), null, 'rekap-ulang.xlsx');
        $this->assertSame([0, 1, 0], [$ulang->dibuat, $ulang->diperbarui, $ulang->fakta_baru]);
        $this->assertSame($faktaAwal, KebutuhanFakta::count());

        // satu angka berubah dan satu sel dikosongkan: versi baru hanya untuk yang berubah; yang kosong dipertahankan
        $path2 = $this->buatExcel([$this->baris('Instansi A', 'Analis SDM Aparatur', [9 => '222/B', 10 => 45800, 22 => 99, 23 => null])]);
        $kedua = $svc->simpan($svc->baca($path2), null, 'rekap2.xlsx');
        $this->assertSame(1, $kedua->fakta_baru);
        $this->assertSame(99, $this->fakta($rekom, 'rekom_bkn', 'pertama'));
        $this->assertSame(12, $this->fakta($rekom, 'rekom_bkn', 'muda'));
        // riwayat: nilai lama tetap tersimpan sebagai bukan terbaru
        $this->assertSame([16], KebutuhanFakta::where('rekomendasi_jf_id', $rekom->id)->where('is_terbaru', false)->pluck('nilai')->all());
        // surat diganti sesuai impor terbaru
        $this->assertSame(['222/B'], $rekom->surats()->where('jenis', 'rekomendasi')->pluck('nomor')->all());
        unlink($path);
        unlink($path2);
    }

    public function test_alur_pratinjau_lalu_impor_hanya_untuk_admin(): void
    {
        Storage::fake('local');
        $path = $this->buatExcel([$this->baris('Instansi A', 'Analis SDM Aparatur'), $this->baris('Instansi A', 'Asesor SDM Aparatur')]);
        $file = fn () => new UploadedFile($path, 'rekap.xlsx', null, null, true);

        $instansi = $this->instansi();
        $profilId = ImporProfil::value('id');
        $this->actingAs($this->user(Role::OperatorInstansi, $instansi))->postJson('/rekomendasi-jf/pratinjau', ['file' => $file(), 'profil_id' => $profilId])->assertForbidden();
        $this->post('/rekomendasi-jf/impor', ['token' => str_repeat('a', 32), 'profil_id' => $profilId])->assertForbidden();

        $admin = $this->user(Role::Admin);
        $res = $this->actingAs($admin)->postJson('/rekomendasi-jf/pratinjau', ['file' => $file(), 'profil_id' => $profilId])->assertOk()
            ->assertJsonPath('ringkas.baris', 2)->assertJsonPath('ringkas.baru', 2);
        $this->assertSame(0, RekomendasiJf::count()); // pratinjau tidak menyimpan

        // token asal-asalan atau path traversal ditolak
        $this->post('/rekomendasi-jf/impor', ['token' => '../../etc/passwd', 'profil_id' => $profilId])->assertSessionHasErrors('token');
        $this->post('/rekomendasi-jf/impor', ['token' => str_repeat('b', 32), 'profil_id' => $profilId])->assertStatus(422);

        $this->post('/rekomendasi-jf/impor', ['token' => $res->json('token'), 'nama_berkas' => 'rekap.xlsx', 'profil_id' => $res->json('profil_id')])->assertSessionHas('success');
        $this->assertSame(2, RekomendasiJf::count());
        $this->assertSame([], Storage::disk('local')->files('rekomendasi-jf-tmp')); // berkas sementara dihapus
        unlink($path);
    }

    public function test_berkas_bukan_xlsx_atau_rusak_ditolak(): void
    {
        Storage::fake('local');
        $admin = $this->user(Role::Admin);
        $this->actingAs($admin)->postJson('/rekomendasi-jf/pratinjau', ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), 'profil_id' => ImporProfil::value('id')])->assertStatus(422);
        $this->postJson('/rekomendasi-jf/pratinjau', ['file' => UploadedFile::fake()->createWithContent('rusak.xlsx', 'bukan excel'), 'profil_id' => ImporProfil::value('id')])
            ->assertStatus(422)->assertJsonStructure(['message']);
        $this->assertSame([], Storage::disk('local')->files('rekomendasi-jf-tmp'));
    }

    public function test_operator_hanya_melihat_instansinya(): void
    {
        $svc = app(RekomendasiJfImportService::class);
        $mine = $this->instansi('MINE');
        $other = $this->instansi('LAIN');
        $refMine = RefInstansiBkn::create(['siasn_id' => 'R1', 'nama' => 'Instansi Saya', 'instansi_id' => $mine->id]);
        $refOther = RefInstansiBkn::create(['siasn_id' => 'R2', 'nama' => 'Instansi Lain', 'instansi_id' => $other->id]);
        $path = $this->buatExcel([$this->baris('Instansi Saya', 'Analis SDM Aparatur'), $this->baris('Instansi Lain', 'Analis SDM Aparatur'), $this->baris('Nama Salah Ketik', 'Analis SDM Aparatur')]);
        $svc->simpan($svc->baca($path), null, 'x.xlsx');
        unlink($path);

        $operator = $this->user(Role::OperatorInstansi, $mine);
        $this->actingAs($operator)->get('/rekomendasi-jf')->assertOk()
            ->assertInertia(fn ($p) => $p->where('ringkasan.baris', 1)->where('datas.data.0.alias.nama', 'Instansi Saya')->where('bolehImpor', false)->where('ringkasan.alias_belum_cocok', 0));

        $milikLain = RekomendasiJf::whereHas('alias', fn ($q) => $q->where('ref_instansi_id', $refOther->id))->firstOrFail();
        $this->get("/rekomendasi-jf/{$milikLain->id}")->assertForbidden();
        $this->get('/rekomendasi-jf/'.RekomendasiJf::whereHas('alias', fn ($q) => $q->where('ref_instansi_id', $refMine->id))->value('id'))->assertOk();

        // kolom angka mengikuti sumber aktif di database
        $this->actingAs($this->user(Role::Admin))->get('/rekomendasi-jf')->assertInertia(fn ($p) => $p->has('sumber', 3)->where('sumber.1.kode', 'rekom_bkn')->has('datas.data.0.s_rekom_bkn'));
    }

    // ------------------------------------------------------------------ master dinamis & manajemen (CRUD)

    public function test_sumber_dan_jabatan_baru_langsung_dipakai_tanpa_ubah_kode(): void
    {
        $admin = $this->user(Role::Admin);
        $this->actingAs($admin);

        // JF baru dengan dua jenjang, sumber baru, dan blok baru di profil impor
        $this->post('/jf-master/jenjang', ['kode' => 'ahli-a', 'nama' => 'Ahli A', 'rumpun' => 'keahlian', 'is_active' => true])->assertSessionHasNoErrors();
        $this->post('/jf-master/jenjang', ['kode' => 'ahli-b', 'nama' => 'Ahli B', 'rumpun' => 'keahlian', 'is_active' => true])->assertSessionHasNoErrors();
        $this->post('/jf-master/jabatan-pokok', ['pembina_id' => $this->pembinaBkn()->id, 'kode' => 'jf-baru', 'nama' => 'Jabatan Fungsional Baru', 'rumpun' => 'keahlian', 'is_active' => true])->assertSessionHasNoErrors();
        $jab = JfJabatanPokok::where('kode', 'jf-baru')->firstOrFail();
        $this->put("/jf-master/jabatan-pokok/{$jab->id}/jenjang", ['jenjang' => [
            ['jenjang_id' => JfJenjang::where('kode', 'ahli-a')->value('id'), 'urutan_kolom' => 1], ['jenjang_id' => JfJenjang::where('kode', 'ahli-b')->value('id'), 'urutan_kolom' => 2],
        ]])->assertSessionHas('success');
        $this->post('/jf-master/sumber', ['pembina_id' => $this->pembinaBkn()->id, 'kode' => 'rekom_x', 'nama' => 'Rekomendasi X', 'jenis' => 'kebutuhan', 'tingkat' => 'instansi', 'sifat' => 'foto', 'adapter' => 'fakta', 'is_active' => true])->assertSessionHasNoErrors();

        $profil = ImporProfil::firstOrFail();
        $pemetaan = $profil->pemetaan;
        $pemetaan['blok'][] = ['sumber' => 'rekom_x', 'kolom_awal' => 'BA', 'lebar' => 2];
        $this->put("/impor-profil/{$profil->id}", ['kode' => $profil->kode, 'nama' => $profil->nama, 'sheet' => $profil->sheet, 'is_active' => true, 'pemetaan' => $pemetaan])->assertSessionHasNoErrors();

        $path = $this->buatExcel([$this->baris('Instansi A', 'Jabatan Fungsional Baru', [17 => 1, 18 => 2, 22 => 5, 23 => 6, 52 => 70, 53 => 80])]);
        $svc = app(RekomendasiJfImportService::class);
        $svc->simpan($svc->baca($path), $admin, 'x.xlsx');
        unlink($path);

        $rekom = RekomendasiJf::whereHas('jabatanPokok', fn ($q) => $q->where('kode', 'jf-baru'))->firstOrFail();
        $this->assertSame([5, 6], [$this->fakta($rekom, 'rekom_bkn', 'ahli-a'), $this->fakta($rekom, 'rekom_bkn', 'ahli-b')]);
        $this->assertSame([70, 80], [$this->fakta($rekom, 'rekom_x', 'ahli-a'), $this->fakta($rekom, 'rekom_x', 'ahli-b')]);
        // sumber baru langsung menjadi kolom di daftar
        $this->get('/rekomendasi-jf')->assertInertia(fn ($p) => $p->has('sumber', 4)->has('datas.data.0.s_rekom_x'));
    }

    public function test_perubahan_tata_letak_cukup_lewat_profil(): void
    {
        $profil = ImporProfil::firstOrFail();
        $pemetaan = $profil->pemetaan;
        // tim data menyisipkan satu kolom di depan: instansi berpindah dari D ke E dan jabatan dari E ke F
        $pemetaan['kolom']['instansi'] = 'E';
        $pemetaan['kolom']['jabatan'] = 'F';
        $profil->update(['pemetaan' => $pemetaan]);

        $path = $this->buatExcel([[4 => 'Instansi Geser', 5 => 'Analis SDM Aparatur', 22 => 7]]);
        $h = app(RekomendasiJfImportService::class)->baca($path);
        unlink($path);

        $this->assertSame(['Instansi Geser'], array_column($h['baris'], 'nama_instansi'));
        $this->assertSame(7, collect($h['baris'][0]['fakta'])->first(fn ($x) => $x['sumber'] === 'rekom_bkn' && $x['jenjang'] === 'pertama')['nilai']);
    }

    public function test_master_tidak_dapat_dihapus_selama_dipakai_dan_hanya_admin(): void
    {
        $svc = app(RekomendasiJfImportService::class);
        $path = $this->buatExcel([$this->baris('Instansi A', 'Analis SDM Aparatur')]);
        $svc->simpan($svc->baca($path), null, 'x.xlsx');
        unlink($path);

        $this->actingAs($this->user(Role::OperatorInstansi, $this->instansi()))->get('/jf-master')->assertForbidden();
        $this->post('/jf-master/sumber', ['kode' => 'z', 'nama' => 'Z'])->assertForbidden();
        $this->get('/alias-instansi')->assertForbidden();
        $this->get('/impor-profil')->assertForbidden();

        $this->actingAs($this->user(Role::Admin));
        $analis = JfJabatanPokok::where('kode', 'analis')->firstOrFail();
        $this->delete("/jf-master/jabatan-pokok/{$analis->id}")->assertSessionHas('error');
        $this->assertTrue($analis->fresh()->exists);
        $sumber = SumberData::where('kode', 'rekom_bkn')->firstOrFail();
        $this->delete("/jf-master/sumber/{$sumber->id}")->assertSessionHas('error');
        $jenjang = JfJenjang::where('kode', 'pertama')->firstOrFail();
        $this->delete("/jf-master/jenjang/{$jenjang->id}")->assertSessionHas('error');
        // kode sumber yang sudah punya data tidak boleh diganti (memutus acuan profil impor)
        $this->put("/jf-master/sumber/{$sumber->id}", ['pembina_id' => $sumber->pembina_id, 'kode' => 'rekom_baru', 'nama' => $sumber->nama, 'jenis' => 'kebutuhan', 'tingkat' => 'instansi', 'sifat' => 'foto', 'adapter' => 'fakta'])->assertSessionHasErrors('kode');
        // jenjang yang sudah punya angka tidak boleh dilepas dari jabatannya
        $this->put("/jf-master/jabatan-pokok/{$analis->id}/jenjang", ['jenjang' => [['jenjang_id' => JfJenjang::where('kode', 'muda')->value('id'), 'urutan_kolom' => 2]]])->assertSessionHas('error');

        // yang belum dipakai boleh dihapus; kode unik dan format dijaga
        $this->post('/jf-master/sumber', ['pembina_id' => $this->pembinaBkn()->id, 'kode' => 'sementara', 'nama' => 'Sementara', 'jenis' => 'kebutuhan', 'tingkat' => 'unit', 'sifat' => 'hidup', 'adapter' => 'fakta'])->assertSessionHasNoErrors();
        $this->delete('/jf-master/sumber/'.SumberData::where('kode', 'sementara')->value('id'))->assertSessionHas('success');
        $this->post('/jf-master/sumber', ['pembina_id' => $this->pembinaBkn()->id, 'kode' => 'asal_gaib', 'nama' => 'X', 'jenis' => 'kebutuhan', 'tingkat' => 'unit', 'sifat' => 'hidup', 'adapter' => 'abk'])->assertSessionHasErrors('adapter'); // asal yang belum berfungsi ditolak
        $this->post('/jf-master/sumber', ['pembina_id' => $this->pembinaBkn()->id, 'kode' => 'rekom_bkn', 'nama' => 'Ganda', 'jenis' => 'kebutuhan', 'tingkat' => 'unit', 'sifat' => 'hidup', 'adapter' => 'fakta'])->assertSessionHasErrors('kode');
        $this->post('/jf-master/sumber', ['pembina_id' => $this->pembinaBkn()->id, 'kode' => 'Kode Salah!', 'nama' => 'X', 'jenis' => 'kebutuhan', 'tingkat' => 'unit', 'sifat' => 'hidup', 'adapter' => 'fakta'])->assertSessionHasErrors('kode');
        $this->post('/jf-master/jabatan-pokok', ['pembina_id' => $this->pembinaBkn()->id, 'kode' => 'x', 'nama' => 'X', 'rumpun' => 'lain'])->assertSessionHasErrors('rumpun');
    }

    public function test_urutan_kolom_jenjang_dapat_ditukar_dan_harus_unik(): void
    {
        $this->actingAs($this->user(Role::Admin));
        $pranata = JfJabatanPokok::where('kode', 'pranata')->firstOrFail();
        [$t, $m, $p] = [JfJenjang::where('kode', 'terampil')->value('id'), JfJenjang::where('kode', 'mahir')->value('id'), JfJenjang::where('kode', 'penyelia')->value('id')];

        $this->put("/jf-master/jabatan-pokok/{$pranata->id}/jenjang", ['jenjang' => [['jenjang_id' => $t, 'urutan_kolom' => 2], ['jenjang_id' => $m, 'urutan_kolom' => 1], ['jenjang_id' => $p, 'urutan_kolom' => 3]]])->assertSessionHas('success');
        $this->assertSame(['mahir', 'terampil', 'penyelia'], $pranata->jenjangs()->pluck('kode')->all());

        $this->put("/jf-master/jabatan-pokok/{$pranata->id}/jenjang", ['jenjang' => [['jenjang_id' => $t, 'urutan_kolom' => 1], ['jenjang_id' => $m, 'urutan_kolom' => 1]]])->assertSessionHasErrors('jenjang.1.urutan_kolom');
    }

    public function test_crud_alias_instansi_dan_pemetaan_ke_referensi(): void
    {
        $svc = app(RekomendasiJfImportService::class);
        $ref = RefInstansiBkn::create(['siasn_id' => 'R1', 'nama' => 'Kementerian Contoh']);
        $path = $this->buatExcel([$this->baris('Nama Salah Ketik', 'Analis SDM Aparatur')]);
        $svc->simpan($svc->baca($path), null, 'x.xlsx');
        $alias = InstansiAlias::firstOrFail();
        $this->assertNull($alias->ref_instansi_id);

        $this->actingAs($this->user(Role::Admin));
        $this->get('/alias-instansi?belum_cocok=1')->assertOk()->assertInertia(fn ($p) => $p->where('ringkasan.belum_cocok', 1)->where('datas.data.0.nama', 'Nama Salah Ketik'));

        // petakan alias; pemetaan bertahan pada impor berikutnya (tidak ditimpa)
        $this->put("/alias-instansi/{$alias->id}", ['nama' => 'Nama Salah Ketik', 'ref_instansi_id' => $ref->id])->assertSessionHas('success');
        $svc->simpan($svc->baca($path), null, 'ulang.xlsx');
        $this->assertSame($ref->id, $alias->fresh()->ref_instansi_id);
        $this->assertSame($ref->id, $svc->baca($path)['baris'][0]['ref_instansi_id']);
        unlink($path);

        // alias manual untuk nama lama; nama ganda (setelah dinormalkan) ditolak
        $this->post('/alias-instansi', ['nama' => 'Kementerian Contoh Lama', 'ref_instansi_id' => $ref->id])->assertSessionHas('success');
        $this->post('/alias-instansi', ['nama' => 'KEMENTERIAN  contoh lama!'])->assertSessionHasErrors('nama');
        $this->assertSame('manual', InstansiAlias::where('nama', 'Kementerian Contoh Lama')->value('asal'));

        // alias yang sudah dipakai tidak dapat dihapus; yang belum dipakai boleh
        $this->delete("/alias-instansi/{$alias->id}")->assertSessionHas('error');
        $this->delete('/alias-instansi/'.InstansiAlias::where('nama', 'Kementerian Contoh Lama')->value('id'))->assertSessionHas('success');
    }

    public function test_crud_profil_impor_memvalidasi_kolom_dan_sumber(): void
    {
        $this->actingAs($this->user(Role::Admin));
        $dasar = ImporProfil::firstOrFail()->pemetaan;
        $kirim = fn (array $pemetaan, string $kode = 'profil-baru') => $this->post('/impor-profil', ['pembina_id' => $this->pembinaBkn()->id, 'kode' => $kode, 'nama' => 'Profil Baru', 'sheet' => 'Sheet1', 'is_active' => true, 'pemetaan' => $pemetaan]);

        $kirim($dasar)->assertSessionHasNoErrors();
        $this->assertSame(2, ImporProfil::count());

        $salah = $dasar;
        $salah['kolom']['instansi'] = '12';                                  // bukan huruf kolom
        $kirim($salah, 'p2')->assertSessionHasErrors('pemetaan.kolom.instansi');
        $salah = $dasar;
        $salah['blok'][0]['sumber'] = 'tidak_ada';                           // sumber tidak dikenal
        $kirim($salah, 'p3')->assertSessionHasErrors('pemetaan.blok.0.sumber');
        $salah = $dasar;
        $salah['blok'] = [];                                                 // minimal satu blok
        $kirim($salah, 'p4')->assertSessionHasErrors('pemetaan.blok');
        $kirim($dasar)->assertSessionHasErrors('kode');                      // kode unik

        $this->delete('/impor-profil/'.ImporProfil::where('kode', 'profil-baru')->value('id'))->assertSessionHas('success');
        $this->assertSame(1, ImporProfil::count());
    }

    public function test_jabatan_simonkeb_dapat_dikaitkan_ke_master_jf_dengan_validasi(): void
    {
        $this->actingAs($this->user(Role::Admin));
        $analis = JfJabatanPokok::where('kode', 'analis')->firstOrFail();
        $pranata = JfJabatanPokok::where('kode', 'pranata')->firstOrFail();
        $pertama = JfJenjang::where('kode', 'pertama')->firstOrFail();
        $terampil = JfJenjang::where('kode', 'terampil')->firstOrFail();
        $dasar = ['kode' => 'JF-UJI-1', 'nama' => 'Analis Uji Ahli Pertama', 'jenis' => 'fungsional', 'bup' => 58, 'is_active' => true];

        // lengkap dan sesuai: tersimpan
        $this->post('/jabatan', $dasar + ['jf_jabatan_pokok_id' => $analis->id, 'jf_jenjang_id' => $pertama->id])->assertSessionHasNoErrors();
        $jab = Jabatan::where('kode', 'JF-UJI-1')->firstOrFail();
        $this->assertSame([$analis->id, $pertama->id], [$jab->jf_jabatan_pokok_id, $jab->jf_jenjang_id]);
        $this->assertSame('Analis SDM Aparatur', $jab->jfJabatanPokok->nama);

        // setengah terisi, atau jenjang yang tidak berlaku untuk jabatan pokok itu: ditolak
        $this->post('/jabatan', ['kode' => 'JF-UJI-2'] + $dasar + ['jf_jabatan_pokok_id' => $analis->id])->assertSessionHasErrors('jf_jenjang_id');
        $this->post('/jabatan', ['kode' => 'JF-UJI-3'] + $dasar + ['jf_jabatan_pokok_id' => $pranata->id, 'jf_jenjang_id' => $pertama->id])->assertSessionHasErrors('jf_jenjang_id');
        $this->post('/jabatan', ['kode' => 'JF-UJI-4'] + $dasar + ['jf_jabatan_pokok_id' => $pranata->id, 'jf_jenjang_id' => $terampil->id])->assertSessionHasNoErrors();

        // tanpa kaitan tetap sah (opsional); jabatan non-fungsional tidak membawa kaitan
        $this->post('/jabatan', ['kode' => 'JF-UJI-5'] + $dasar)->assertSessionHasNoErrors();
        $this->assertNull(Jabatan::where('kode', 'JF-UJI-5')->value('jf_jabatan_pokok_id'));
        $this->post('/jabatan', ['kode' => 'ADM-UJI', 'jenis' => 'administrator'] + $dasar + ['jf_jabatan_pokok_id' => $analis->id, 'jf_jenjang_id' => $pertama->id])->assertSessionHasNoErrors();
        $this->assertNull(Jabatan::where('kode', 'ADM-UJI')->value('jf_jabatan_pokok_id'));

        // form menyediakan pilihan jabatan pokok beserta jenjangnya; daftar menampilkan kaitannya
        $this->get('/jabatan/create')->assertInertia(fn ($p) => $p->has('jfOptions', 4)->has('jfOptions.0.jenjang', 4));
        $this->get('/jabatan?q=JF-UJI-1')->assertInertia(fn ($p) => $p->where('datas.data.0.jf_jabatan_pokok.nama', 'Analis SDM Aparatur')->where('datas.data.0.jf_jenjang.nama', 'Pertama'));
    }
}
