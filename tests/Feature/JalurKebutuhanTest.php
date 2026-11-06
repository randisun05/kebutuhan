<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Exports\ArrayExport;
use App\Models\KebutuhanSementara;
use App\Models\Peringatan;
use App\Services\MonitoringService;
use App\Services\PeringatanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

/** Dua jalur kebutuhan: standar (ABK) dan manual dari existing (kebutuhan sementara). */
class JalurKebutuhanTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    public function test_isi_dari_existing_hanya_mengisi_posisi_tanpa_abk_dan_tanpa_isian(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        [$a, $b, $c] = [$this->jabatan('A'), $this->jabatan('B'), $this->jabatan('C')];
        $this->pegawai($unit, $a, 3);
        $this->pegawai($unit, $b, 2);
        $this->pegawai($unit, $c, 4);
        $this->abk($unit, $b, 7); // sudah ada ABK final
        KebutuhanSementara::create(['instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $c->id, 'jumlah' => 9]); // sudah diisi

        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $this->actingAs($operator)->post('/kebutuhan-sementara/isi-existing', ['instansi_id' => $instansi->id])
            ->assertSessionHas('success');

        $this->assertSame(3, KebutuhanSementara::where('jabatan_id', $a->id)->value('jumlah'));
        $this->assertSame(0, KebutuhanSementara::where('jabatan_id', $b->id)->count());
        $this->assertSame(9, KebutuhanSementara::where('jabatan_id', $c->id)->value('jumlah'));

        // kebutuhan = existing untuk A, ABK untuk B, isian untuk C
        $s = app(MonitoringService::class)->summary(['instansi_id' => $instansi->id]);
        $this->assertSame(3 + 7 + 9, $s['kebutuhan']);
    }

    public function test_isi_dari_existing_satu_unit_dan_batas_instansi(): void
    {
        $instansi = $this->instansi('A');
        $lain = $this->instansi('B');
        $induk = $this->unit($instansi, 'P');
        $anak = $this->unit($instansi, 'Q', $induk);
        $jab = $this->jabatan('J');
        $this->pegawai($induk, $jab, 1);
        $this->pegawai($anak, $jab, 2);
        $unitLain = $this->unit($lain, 'X');
        $this->pegawai($unitLain, $jab, 5);

        $operator = $this->user(Role::OperatorInstansi, $instansi);
        // operator tidak boleh mengisi instansi lain
        $this->actingAs($operator)->post('/kebutuhan-sementara/isi-existing', ['instansi_id' => $lain->id])->assertForbidden();
        // unit milik instansi lain ditolak
        $this->post('/kebutuhan-sementara/isi-existing', ['instansi_id' => $instansi->id, 'unit_kerja_id' => $unitLain->id])->assertForbidden();

        // hanya anak (tanpa sub-unit induk)
        $this->post('/kebutuhan-sementara/isi-existing', ['instansi_id' => $instansi->id, 'unit_kerja_id' => $anak->id, 'sub' => 0])->assertSessionHas('success');
        $this->assertSame([$anak->id], KebutuhanSementara::pluck('unit_kerja_id')->all());
    }

    public function test_hanya_admin_dan_operator_yang_boleh_mengisi(): void
    {
        $instansi = $this->instansi();
        $this->actingAs($this->user(Role::VerifikatorBkn))
            ->post('/kebutuhan-sementara/isi-existing', ['instansi_id' => $instansi->id])->assertForbidden();
    }

    public function test_impor_excel_membuat_memperbarui_dan_menghapus(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'UK-1');
        $jab = $this->jabatan('JF-1');
        $jab2 = $this->jabatan('JF-2');
        KebutuhanSementara::create(['instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $jab2->id, 'jumlah' => 4]);

        Storage::fake('local');
        Excel::store(new ArrayExport(['kode_unit', 'kode_jabatan', 'jumlah', 'dasar'], [
            ['UK-1', 'JF-1', 6, 'Peta 2024'],
            ['UK-1', 'JF-2', '0', ''],
            ['UK-1', 'XXX', 3, ''],
            ['UK-1', 'JF-1', -2, ''],
        ]), 'k.xlsx', 'local');
        $file = new UploadedFile(Storage::disk('local')->path('k.xlsx'), 'k.xlsx', null, null, true);

        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $this->actingAs($operator)->post('/kebutuhan-sementara/import', ['instansi_id' => $instansi->id, 'file' => $file])
            ->assertSessionHas('warning');

        $this->assertSame(6, KebutuhanSementara::where('jabatan_id', $jab->id)->value('jumlah'));
        $this->assertSame(0, KebutuhanSementara::where('jabatan_id', $jab2->id)->count());
    }

    public function test_template_dapat_diunduh(): void
    {
        $this->actingAs($this->user(Role::Admin))->get('/kebutuhan-sementara/template')->assertOk();
    }

    public function test_jalur_manual_tidak_membuat_peringatan_abk_kedaluwarsa(): void
    {
        $standar = $this->instansi('S');
        $manual = $this->instansi('M');
        $manual->update(['jalur_kebutuhan' => 'manual']);
        $jab = $this->jabatan('J');
        $this->pegawai($this->unit($standar, 'U1'), $jab, 2);
        $this->pegawai($this->unit($manual, 'U2'), $jab, 2);

        app(PeringatanService::class)->deteksi(false);

        $this->assertSame(1, Peringatan::where('kode', 'abk_kedaluwarsa')->where('instansi_id', $standar->id)->count());
        $this->assertSame(0, Peringatan::where('kode', 'abk_kedaluwarsa')->where('instansi_id', $manual->id)->count());
    }

    public function test_admin_mengubah_jalur_instansi(): void
    {
        $instansi = $this->instansi();
        $this->actingAs($this->user(Role::Admin))->put("/instansi/{$instansi->id}", [
            'kode' => $instansi->kode, 'nama' => $instansi->nama, 'jenis' => 'kabupaten', 'jalur_kebutuhan' => 'manual', 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame('manual', $instansi->fresh()->jalur_kebutuhan);

        $this->put("/instansi/{$instansi->id}", [
            'kode' => $instansi->kode, 'nama' => $instansi->nama, 'jenis' => 'kabupaten', 'jalur_kebutuhan' => 'lain',
        ])->assertSessionHasErrors('jalur_kebutuhan');
    }
}
