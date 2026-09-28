<?php

namespace Tests\Feature;

use App\Services\MonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class MonitoringServiceTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    public function test_kekurangan_dan_kelebihan_dihitung_per_posisi_tanpa_saling_menutupi(): void
    {
        $instansi = $this->instansi();
        $induk = $this->unit($instansi, 'Induk');
        $anak = $this->unit($instansi, 'Anak', $induk);
        $analis = $this->jabatan('ANALIS');
        $pelaksana = $this->jabatan('PLK', 'pelaksana');

        $this->abk($anak, $analis, 3);          // butuh 3, ada 1 -> kurang 2
        $this->pegawai($anak, $analis, 1);
        $this->abk($induk, $pelaksana, 1);      // butuh 1, ada 3 -> lebih 2
        $this->pegawai($induk, $pelaksana, 3);
        $this->abk($induk, $analis, 2);         // butuh 2, kosong
        $this->abk($anak, $pelaksana, 5, 'draft'); // draft tidak dihitung
        $this->pegawai($anak, $pelaksana, 1);   // tanpa ABK final -> lebih 1

        $summary = app(MonitoringService::class)->summary(['instansi_id' => $instansi->id]);

        $this->assertSame(6, $summary['kebutuhan']);
        $this->assertSame(5, $summary['existing']);
        $this->assertSame(4, $summary['kurang']);
        $this->assertSame(3, $summary['lebih']);
        $this->assertSame(1, $summary['jabatan_kosong']);
        $this->assertSame(1, $summary['tanpa_abk']);
        $this->assertSame(4, $summary['jumlah_jabatan']);
    }

    public function test_rekap_unit_mengakumulasi_sub_unit_ke_induk(): void
    {
        $instansi = $this->instansi();
        $induk = $this->unit($instansi, 'Induk');
        $anak = $this->unit($instansi, 'Anak', $induk);
        $cucu = $this->unit($instansi, 'Cucu', $anak);
        $jab = $this->jabatan('J1');

        $this->abk($induk, $jab, 1);
        $this->abk($anak, $jab, 2);
        $this->abk($cucu, $jab, 4);
        $this->pegawai($cucu, $jab, 1);

        $units = app(MonitoringService::class)->byUnit($instansi->id)->keyBy('id');

        $this->assertSame(1, $units[$induk->id]['own']['kebutuhan']);
        $this->assertSame(7, $units[$induk->id]['total']['kebutuhan']);
        $this->assertSame(6, $units[$anak->id]['total']['kebutuhan']);
        $this->assertSame(1, $units[$induk->id]['total']['existing']);
        $this->assertSame(2, $units[$cucu->id]['depth']);
    }

    public function test_proyeksi_pensiun_memakai_bup_jabatan(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('J58', 'fungsional', 58);

        $this->abk($unit, $jab, 3);
        $this->pegawai($unit, $jab, 1, now()->subYears(56)->toDateString()); // pensiun ~2 th lagi
        $this->pegawai($unit, $jab, 1, now()->subYears(30)->toDateString());

        $summary = app(MonitoringService::class)->summary(['instansi_id' => $instansi->id]);

        $this->assertSame(1, $summary['pensiun']);
    }

    public function test_filter_status_kosong_dan_jenis_jabatan(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jf = $this->jabatan('JF');
        $plk = $this->jabatan('PLK', 'pelaksana');
        $this->abk($unit, $jf, 2);
        $this->abk($unit, $plk, 1);
        $this->pegawai($unit, $plk, 1);

        $service = app(MonitoringService::class);

        $kosong = $service->positions(['status' => 'kosong']);
        $this->assertCount(1, $kosong);
        $this->assertSame('kosong', $kosong->first()['status']);

        $this->assertSame(1, $service->summary(['jenis' => 'pelaksana'])['kebutuhan']);
    }
}
