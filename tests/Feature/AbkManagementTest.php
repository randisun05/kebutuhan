<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AnjabAbk;
use App\Services\MonitoringService;
use App\Services\ProyeksiService;
use App\Services\RedistribusiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class AbkManagementTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    public function test_volume_per_bulan_minggu_hari_disetahunkan(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $operator = $this->user(Role::OperatorInstansi, $instansi);

        $this->actingAs($operator)->post('/anjab', [
            'instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'tahun' => 2026,
            'waktu_kerja_efektif' => 75000, 'status' => 'draft',
            'informasi' => ['bahan_kerja' => ['Data pegawai', '  ', ''], 'prestasi_diharapkan' => 'Laporan tepat waktu'],
            'uraian' => [
                ['uraian_tugas' => 'Bulanan', 'volume' => 100, 'satuan_periode' => 'bulan', 'norma_waktu' => 30],  // 36.000
                ['uraian_tugas' => 'Harian', 'volume' => 2, 'satuan_periode' => 'hari', 'norma_waktu' => 60],     // 30.000
                ['uraian_tugas' => 'Mingguan', 'volume' => 1, 'satuan_periode' => 'minggu', 'norma_waktu' => 120], // 6.000
            ],
        ])->assertSessionHasNoErrors();

        $anjab = AnjabAbk::firstOrFail();
        $this->assertEquals(72000, $anjab->total_beban_kerja);
        $this->assertEquals(0.96, $anjab->kebutuhan_hitung);
        $this->assertSame(1, $anjab->kebutuhan);
        $this->assertSame(['Data pegawai'], $anjab->informasi['bahan_kerja']);
    }

    public function test_monitoring_memakai_abk_final_tahun_terbaru(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $lama = $this->abk($unit, $jab, 2);
        $baru = $lama->replicate(['kebutuhan']);
        $baru->fill(['tahun' => 2027])->save();
        $baru->uraianTugas()->create(['uraian_tugas' => 'X', 'volume' => 75000 * 5 / 60, 'norma_waktu' => 60]);
        $baru->recalculate();

        $monitoring = app(MonitoringService::class);
        $this->assertSame(5, $monitoring->summary(['instansi_id' => $instansi->id])['kebutuhan']);

        // draft tahun terbaru diabaikan -> kembali ke ABK final sebelumnya
        $baru->update(['status' => 'draft']);
        $this->assertSame(2, $monitoring->summary(['instansi_id' => $instansi->id])['kebutuhan']);
    }

    public function test_salin_abk_ke_tahun_berikut_dan_finalisasi(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $anjab = $this->abk($unit, $jab, 3);
        $operator = $this->user(Role::OperatorInstansi, $instansi);

        $this->actingAs($operator)->post("/anjab/{$anjab->id}/duplicate", ['tahun' => 2027])->assertSessionHasNoErrors();
        $salinan = AnjabAbk::where('tahun', 2027)->firstOrFail();
        $this->assertSame('draft', $salinan->status);
        $this->assertSame(3, $salinan->kebutuhan);
        $this->assertSame(1, $salinan->uraianTugas()->count());

        $this->post("/anjab/{$anjab->id}/duplicate", ['tahun' => 2027])->assertSessionHasErrors('tahun');

        $this->post("/anjab/{$salinan->id}/status")->assertRedirect();
        $salinan->refresh();
        $this->assertSame('final', $salinan->status);
        $this->assertSame($operator->id, $salinan->finalized_by);
    }

    public function test_impor_abk_dari_excel(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'UK-1');
        $this->jabatan('JF-1');
        $operator = $this->user(Role::OperatorInstansi, $instansi);

        $csv = "kode_unit,kode_jabatan,tahun,uraian_tugas,hasil_kerja,volume,satuan_periode,norma_waktu\n"
            ."UK-1,JF-1,2026,Tugas A,Dokumen,10,bulan,300\n"
            ."UK-1,JF-1,2026,Tugas B,Laporan,1,hari,90\n"
            ."UK-X,JF-1,2026,Salah,Dokumen,1,tahun,10\n";
        $file = UploadedFile::fake()->createWithContent('abk.csv', $csv);

        $this->actingAs($operator)->post('/anjab-import', ['instansi_id' => $instansi->id, 'file' => $file])->assertRedirect();

        $anjab = AnjabAbk::with('uraianTugas')->firstOrFail();
        $this->assertSame($unit->id, $anjab->unit_kerja_id);
        $this->assertCount(2, $anjab->uraianTugas);
        $this->assertEquals(10 * 12 * 300 + 250 * 90, $anjab->total_beban_kerja);
        $this->assertSame('draft', $anjab->status);
    }

    public function test_proyeksi_menghitung_pensiun_pertumbuhan_dan_rencana_formasi(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF', 'fungsional', 58);
        $anjab = $this->abk($unit, $jab, 4);
        $anjab->update(['tahun' => (int) now()->format('Y'), 'pertumbuhan_beban' => 0]);

        $mulai = (int) now()->format('Y') + 1;
        // 3 pegawai: satu pensiun pada tahun ke-2 proyeksi
        $this->pegawai($unit, $jab, 2, '1990-01-01');
        $this->pegawai($unit, $jab, 1, ($mulai + 1 - 58).'-03-10');

        $hasil = app(ProyeksiService::class)->hitung($instansi->id, [], $mulai);
        $row = $hasil['rows'][0];

        $this->assertSame(1, $row['tahun'][$mulai]['rencana']);       // 4 - 3
        $this->assertSame(1, $row['tahun'][$mulai + 1]['pensiun']);
        $this->assertSame(1, $row['tahun'][$mulai + 1]['rencana']);   // tambahan karena pensiun
        $this->assertSame(0, $row['tahun'][$mulai + 2]['rencana']);
        $this->assertSame(2, $row['total_rencana']);

        $anjab->update(['pertumbuhan_beban' => 10]);
        $hasil = app(ProyeksiService::class)->hitung($instansi->id, [], $mulai);
        $akhir = $mulai + 4;
        $this->assertSame((int) round(4 * 1.1 ** ($akhir - $anjab->tahun)), $hasil['rows'][0]['tahun'][$akhir]['kebutuhan']);
    }

    public function test_redistribusi_memasangkan_kelebihan_dengan_kekurangan_jabatan_sama(): void
    {
        $instansi = $this->instansi();
        $a = $this->unit($instansi, 'A');
        $b = $this->unit($instansi, 'B');
        $jab = $this->jabatan('JF');
        $lain = $this->jabatan('LAIN');
        $this->abk($a, $jab, 1);
        $this->pegawai($a, $jab, 4);   // lebih 3
        $this->abk($b, $jab, 3);
        $this->pegawai($b, $jab, 1);   // kurang 2
        $this->abk($b, $lain, 2);      // kurang 2, tanpa sumber

        $hasil = app(RedistribusiService::class)->saran($instansi->id);

        $this->assertCount(1, $hasil['saran']);
        $this->assertSame(2, $hasil['saran'][0]['jumlah']);
        $this->assertSame($a->id, $hasil['saran'][0]['dari_unit_id']);
        $this->assertSame(2, $hasil['total_sisa_kurang']);
    }

    public function test_halaman_abk_dan_perencanaan_dapat_dibuka(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $anjab = $this->abk($unit, $jab, 2);
        $this->pegawai($unit, $jab, 1);
        Excel::fake();

        foreach ([Role::Admin, Role::OperatorInstansi, Role::VerifikatorBkn] as $role) {
            $this->actingAs($this->user($role, $role === Role::OperatorInstansi ? $instansi : null));
            $this->get("/anjab/{$anjab->id}")->assertOk();
            $this->get("/anjab-rekap-unit?instansi_id={$instansi->id}")->assertOk();
            $this->get("/proyeksi?instansi_id={$instansi->id}")->assertOk();
            $this->get("/redistribusi?instansi_id={$instansi->id}")->assertOk();
            $this->get("/peta-jabatan?instansi_id={$instansi->id}")->assertOk();
            $this->get("/anjab/{$anjab->id}/pdf")->assertOk();
            $this->get('/anjab-export')->assertOk();
            $this->get("/proyeksi/export?instansi_id={$instansi->id}")->assertOk();
        }
    }
}
