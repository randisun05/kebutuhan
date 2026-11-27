<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\RefInstansiBkn;
use App\Models\RekomendasiJf;
use App\Models\RekomendasiJfPetaInstansi;
use App\Services\RekomendasiJfImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

/** Impor Excel rekap kebutuhan JF MASN (rekomendasi BKN dan penetapan KemenPANRB). */
class RekomendasiJfTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    /** Buat berkas xlsx dengan tata letak seperti sheet "Input Data (Instansi Baru)". */
    private function buatExcel(array $baris, string $sheet = RekomendasiJfImportService::SHEET): string
    {
        $wb = new Spreadsheet;
        $ws = $wb->getActiveSheet()->setTitle($sheet);
        $ws->fromArray(['NO', 'x', 'x', 'NAMA INSTANSI', 'JABATAN'], null, 'A2'); // baris judul
        $ws->fromArray([null, null, 1.0, 2.0, 3.0], null, 'A3');                    // baris nomor kolom
        $r = 4;
        foreach ($baris as $b) {
            $row = array_fill(0, 61, null);
            foreach ($b as $kolom => $nilai) {
                $row[$kolom] = $nilai;
            }
            $ws->fromArray($row, null, 'A'.$r++);
        }
        $path = sys_get_temp_dir().'/rekom-'.uniqid().'.xlsx';
        (new Xlsx($wb))->save($path);

        return $path;
    }

    private function baris(string $instansi, string $jabatan, array $lebih = []): array
    {
        return $lebih + [3 => $instansi, 4 => $jabatan, 5 => 'KAB', 6 => 'POKJA 2', 7 => 'DONE', 14 => 'Rekom baru',
            17 => 2, 18 => 3, 19 => 1, 20 => 0, 22 => 16, 23 => 12, 24 => 9, 25 => 1, 37 => 16, 38 => 10, 39 => 9, 40 => null,
            41 => 'B/3800/M.SM.01.00/2025', 42 => 45900, 43 => 'Sekretaris Kementerian', 49 => 'BKN Pusat', 50 => 'Instansi baru'];
    }

    public function test_baca_memetakan_jenjang_surat_dan_instansi(): void
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

        $analis = collect($h['baris'])->firstWhere('jabatan_pokok', 'Analis SDM Aparatur');
        $this->assertNotNull($analis['ref_instansi_id']); // "Kab." dinormalkan ke "Kabupaten"
        $this->assertSame('keahlian', $analis['rumpun']);
        $this->assertSame(['pertama', 'muda', 'madya', 'utama'], array_keys($analis['jenjang']));
        $this->assertSame(['bezetting_rekom' => 2, 'rekom_bkn' => 16, 'penetapan_menpan' => 16], $analis['jenjang']['pertama']);
        $this->assertSame('ditetapkan', $analis['status_menpan']);
        $this->assertSame('POKJA 2', $analis['pokja']);

        // dua surat rekomendasi dari satu sel, tanggal teks Indonesia, tautan hanya pada surat pertama
        $rekom = collect($analis['surat'])->where('jenis', 'rekomendasi')->values();
        $this->assertSame(['4401/B-BJ.02.01/SD/C/2024', '6778/B-BJ.01.01/SD/C/2026'], $rekom->pluck('nomor')->all());
        $this->assertSame(['2024-06-27', '2026-10-15'], $rekom->pluck('tanggal')->all());
        $this->assertSame('https://drive.example/surat', $rekom[0]['tautan']);
        $this->assertArrayNotHasKey('tautan', $rekom[1]);
        // tanggal berupa nomor seri Excel
        $menpan = collect($analis['surat'])->firstWhere('jenis', 'penetapan_menpan');
        $this->assertSame('2025-08-31', $menpan['tanggal']);

        // Pranata: tiga jenjang keterampilan; angka pada jenjang keempat diabaikan dan diperingatkan
        $pranata = collect($h['baris'])->firstWhere('jabatan_pokok', 'Pranata SDM Aparatur');
        $this->assertSame(['terampil', 'mahir', 'penyelia'], array_keys($pranata['jenjang']));
        $this->assertTrue(collect($h['peringatan'])->contains(fn ($p) => str_contains($p, 'jenjang keempat')));

        // baris tanpa angka tetap tercatat (cakupan "belum usul") tetapi tanpa jenjang dan tanpa penetapan
        $belum = collect($h['baris'])->firstWhere('nama_instansi', 'Instansi Antah Berantah');
        $this->assertSame([], $belum['jenjang']);
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
        $this->assertSame(['bezetting_rekom' => 16, 'rekom_bkn' => 45, 'penetapan_menpan' => 33], $b['jenjang']['pertama']);
        $menpan = collect($b['surat'])->firstWhere('jenis', 'penetapan_menpan');
        $this->assertSame(['B/9/M.SM.01.00/2025', '2025-08-31'], [$menpan['nomor'], $menpan['tanggal']]);
        $rekom = collect($b['surat'])->firstWhere('jenis', 'rekomendasi');
        $this->assertSame(['111/B/2025', '2025-06-04'], [$rekom['nomor'], $rekom['tanggal']]);
    }

    public function test_sheet_tidak_ada_ditolak_dengan_pesan_jelas(): void
    {
        $path = $this->buatExcel([$this->baris('X', 'Analis SDM Aparatur')], 'Sheet Lain');

        $this->expectExceptionMessage('Sheet "'.RekomendasiJfImportService::SHEET.'" tidak ditemukan');
        app(RekomendasiJfImportService::class)->baca($path);
    }

    public function test_simpan_idempoten_memperbarui_bukan_menggandakan(): void
    {
        $svc = app(RekomendasiJfImportService::class);
        $path = $this->buatExcel([$this->baris('Instansi A', 'Analis SDM Aparatur', [9 => '111/B', 10 => 45700])]);

        $pertama = $svc->simpan($svc->baca($path), null, 'rekap.xlsx');
        $this->assertSame([1, 0], [$pertama->dibuat, $pertama->diperbarui]);
        $this->assertSame(1, RekomendasiJf::count());

        // impor ulang dengan angka berubah: baris diperbarui, jenjang dan surat diganti, bukan ditambah
        $path2 = $this->buatExcel([$this->baris('Instansi A', 'Analis SDM Aparatur', [9 => '222/B', 10 => 45800, 22 => 99])]);
        $kedua = $svc->simpan($svc->baca($path2), null, 'rekap2.xlsx');
        $this->assertSame([0, 1], [$kedua->dibuat, $kedua->diperbarui]);

        $rekom = RekomendasiJf::firstOrFail();
        $this->assertSame(1, RekomendasiJf::count());
        $this->assertSame(99, $rekom->jenjangs()->where('jenjang', 'pertama')->value('rekom_bkn'));
        $this->assertSame(['222/B'], $rekom->surats()->where('jenis', 'rekomendasi')->pluck('nomor')->all());
        // lowongan dihitung ulang, tidak disimpan: bezetting 2 − rekom 99
        $this->assertSame(-97, $rekom->jenjangs()->where('jenjang', 'pertama')->first()->lowongan);
        unlink($path);
        unlink($path2);
    }

    public function test_alur_pratinjau_lalu_impor_hanya_untuk_admin(): void
    {
        Storage::fake('local');
        $path = $this->buatExcel([$this->baris('Instansi A', 'Analis SDM Aparatur'), $this->baris('Instansi A', 'Asesor SDM Aparatur')]);
        $file = fn () => new UploadedFile($path, 'rekap.xlsx', null, null, true);

        $instansi = $this->instansi();
        $this->actingAs($this->user(Role::OperatorInstansi, $instansi))->postJson('/rekomendasi-jf/pratinjau', ['file' => $file()])->assertForbidden();
        $this->post('/rekomendasi-jf/impor', ['token' => str_repeat('a', 32)])->assertForbidden();

        $admin = $this->user(Role::Admin);
        $res = $this->actingAs($admin)->postJson('/rekomendasi-jf/pratinjau', ['file' => $file()])->assertOk()
            ->assertJsonPath('ringkas.baris', 2)->assertJsonPath('ringkas.baru', 2);
        $this->assertSame(0, RekomendasiJf::count()); // pratinjau tidak menyimpan

        // token asal-asalan atau path traversal ditolak
        $this->post('/rekomendasi-jf/impor', ['token' => '../../etc/passwd'])->assertSessionHasErrors('token');
        $this->post('/rekomendasi-jf/impor', ['token' => str_repeat('b', 32)])->assertStatus(422);

        $this->post('/rekomendasi-jf/impor', ['token' => $res->json('token'), 'nama_berkas' => 'rekap.xlsx'])->assertSessionHas('success');
        $this->assertSame(2, RekomendasiJf::count());
        // berkas sementara dihapus setelah impor
        $this->assertSame([], Storage::disk('local')->files('rekomendasi-jf-tmp'));
        unlink($path);
    }

    public function test_berkas_bukan_xlsx_atau_rusak_ditolak(): void
    {
        Storage::fake('local');
        $admin = $this->user(Role::Admin);
        $this->actingAs($admin)->postJson('/rekomendasi-jf/pratinjau', ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertStatus(422);
        $this->postJson('/rekomendasi-jf/pratinjau', ['file' => UploadedFile::fake()->createWithContent('rusak.xlsx', 'bukan excel')])
            ->assertStatus(422)->assertJsonStructure(['message']);
        $this->assertSame([], Storage::disk('local')->files('rekomendasi-jf-tmp'));
    }

    public function test_operator_hanya_melihat_instansinya_dan_admin_memetakan_instansi(): void
    {
        $svc = app(RekomendasiJfImportService::class);
        $mine = $this->instansi('MINE');
        $other = $this->instansi('LAIN');
        $refMine = RefInstansiBkn::create(['siasn_id' => 'R1', 'nama' => 'Instansi Saya', 'instansi_id' => $mine->id]);
        $refOther = RefInstansiBkn::create(['siasn_id' => 'R2', 'nama' => 'Instansi Lain', 'instansi_id' => $other->id]);
        $path = $this->buatExcel([
            $this->baris('Instansi Saya', 'Analis SDM Aparatur'), $this->baris('Instansi Lain', 'Analis SDM Aparatur'), $this->baris('Nama Salah Ketik', 'Analis SDM Aparatur'),
        ]);
        $svc->simpan($svc->baca($path), null, 'x.xlsx');
        unlink($path);

        $operator = $this->user(Role::OperatorInstansi, $mine);
        $this->actingAs($operator)->get('/rekomendasi-jf')->assertOk()
            ->assertInertia(fn ($p) => $p->where('ringkasan.baris', 1)->where('datas.data.0.nama_instansi', 'Instansi Saya')->where('bolehImpor', false)->has('belumCocok', 0));

        $milikLain = RekomendasiJf::where('ref_instansi_id', $refOther->id)->firstOrFail();
        $this->get("/rekomendasi-jf/{$milikLain->id}")->assertForbidden();
        $this->get('/rekomendasi-jf/'.RekomendasiJf::where('ref_instansi_id', $refMine->id)->value('id'))->assertOk();

        // operator tidak boleh memetakan; admin boleh, dan pemetaan bertahan untuk impor berikutnya
        $normal = RekomendasiJf::whereNull('ref_instansi_id')->value('nama_instansi_normal');
        $this->post('/rekomendasi-jf/petakan-instansi', ['nama_normal' => $normal, 'ref_instansi_id' => $refMine->id])->assertForbidden();
        $this->actingAs($this->user(Role::Admin))->post('/rekomendasi-jf/petakan-instansi', ['nama_normal' => $normal, 'ref_instansi_id' => $refMine->id])->assertSessionHas('success');
        $this->assertSame($refMine->id, RekomendasiJf::where('nama_instansi_normal', $normal)->value('ref_instansi_id'));
        $this->assertSame(1, RekomendasiJfPetaInstansi::count());

        $path2 = $this->buatExcel([$this->baris('Nama Salah Ketik', 'Analis SDM Aparatur')]);
        $this->assertSame($refMine->id, app(RekomendasiJfImportService::class)->baca($path2)['baris'][0]['ref_instansi_id']);
        unlink($path2);
    }
}
