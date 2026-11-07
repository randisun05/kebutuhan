<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UsulanStatus;
use App\Exports\ArrayExport;
use App\Imports\KebutuhanSementaraImport;
use App\Models\KebutuhanSementara;
use App\Models\KebutuhanSementaraBatch;
use App\Models\Peringatan;
use App\Models\Usulan;
use App\Models\UsulanDetail;
use App\Services\LaporanService;
use App\Services\MonitoringService;
use App\Services\PeringatanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_isi_dari_formasi_yang_ditetapkan(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        [$a, $b] = [$this->jabatan('A'), $this->jabatan('B')];
        $this->pegawai($unit, $a, 1);
        $ditetapkan = Usulan::create(['instansi_id' => $instansi->id, 'tahun' => 2026, 'perihal' => 'U1', 'status' => UsulanStatus::Ditetapkan]);
        $draft = Usulan::create(['instansi_id' => $instansi->id, 'tahun' => 2027, 'perihal' => 'U2', 'status' => UsulanStatus::Draft]);
        foreach ([[$ditetapkan, $a, 4], [$ditetapkan, $b, 2], [$draft, $b, 9]] as [$u, $j, $n]) {
            UsulanDetail::create(['usulan_id' => $u->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $j->id, 'jumlah_usul' => $n, 'jumlah_ditetapkan' => $u === $draft ? null : $n]);
        }

        $this->actingAs($this->user(Role::OperatorInstansi, $instansi))
            ->post('/kebutuhan-sementara/isi-existing', ['instansi_id' => $instansi->id, 'sumber' => 'formasi'])
            ->assertSessionHas('success');

        // hanya usulan berstatus ditetapkan yang dihitung; existing tidak dipakai
        $this->assertSame(4, KebutuhanSementara::where('jabatan_id', $a->id)->value('jumlah'));
        $this->assertSame(2, KebutuhanSementara::where('jabatan_id', $b->id)->value('jumlah'));
        $this->assertStringContainsString('Formasi ditetapkan', KebutuhanSementara::first()->dasar);
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

    public function test_pratinjau_tidak_menulis_dan_isi_dapat_dibatalkan(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        [$a, $b] = [$this->jabatan('A'), $this->jabatan('B')];
        $this->pegawai($unit, $a, 3);
        $this->pegawai($unit, $b, 2);
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $this->actingAs($operator);

        $this->postJson('/kebutuhan-sementara/pratinjau', ['instansi_id' => $instansi->id])
            ->assertOk()->assertJson(['dibuat' => 2, 'total_kebutuhan' => 5]);
        $this->assertSame(0, KebutuhanSementara::count());

        $this->post('/kebutuhan-sementara/isi-existing', ['instansi_id' => $instansi->id])->assertSessionHas('success');
        $this->assertSame(2, KebutuhanSementara::count());
        $batch = KebutuhanSementaraBatch::firstOrFail();
        $this->assertSame(2, $batch->jumlah_dibuat);

        // satu baris diubah pengguna setelah pengisian: dipertahankan saat dibatalkan
        $diubah = KebutuhanSementara::where('jabatan_id', $a->id)->first();
        $diubah->forceFill(['jumlah' => 9, 'updated_at' => now()->addMinutes(5)])->saveQuietly();

        $this->post("/kebutuhan-sementara/batal/{$batch->uuid}")->assertSessionHas('success');
        $this->assertSame(1, KebutuhanSementara::count());
        $this->assertSame(9, KebutuhanSementara::first()->jumlah);
        $this->assertNotNull($batch->fresh()->dibatalkan_at);

        // tidak bisa dibatalkan dua kali
        $this->post("/kebutuhan-sementara/batal/{$batch->uuid}")->assertStatus(422);

        // operator instansi lain tidak boleh membatalkan
        $lain = $this->user(Role::OperatorInstansi, $this->instansi('LAIN'));
        $baru = KebutuhanSementaraBatch::create(['uuid' => (string) Str::uuid(), 'instansi_id' => $instansi->id, 'sumber' => 'existing', 'jumlah_dibuat' => 1]);
        $this->actingAs($lain)->post("/kebutuhan-sementara/batal/{$baru->uuid}")->assertForbidden();
    }

    public function test_persetujuan_isian_operator_pada_instansi_yang_mewajibkan(): void
    {
        $instansi = $this->instansi();
        $instansi->update(['kebutuhan_perlu_persetujuan' => true]);
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('A');
        $this->pegawai($unit, $jab, 2);
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $monitoring = app(MonitoringService::class);

        $this->actingAs($operator)->post('/kebutuhan-sementara', ['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah' => 6])
            ->assertSessionHas('info');
        $this->assertSame('menunggu', KebutuhanSementara::first()->status);
        // belum dipakai di perhitungan
        $this->assertSame(0, $monitoring->summary(['instansi_id' => $instansi->id])['kebutuhan']);

        // operator tidak dapat menyetujui
        $this->post('/kebutuhan-sementara/persetujuan', ['instansi_id' => $instansi->id, 'aksi' => 'setujui'])->assertForbidden();

        $admin = $this->user(Role::Admin);
        $this->actingAs($admin)
            ->post('/kebutuhan-sementara/persetujuan', ['instansi_id' => $instansi->id, 'aksi' => 'setujui'])->assertSessionHas('success');
        $this->assertSame(6, $monitoring->summary(['instansi_id' => $instansi->id])['kebutuhan']);

        // isian administrator langsung disetujui; penolakan menghapus
        $this->actingAs($operator)->post('/kebutuhan-sementara', ['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah' => 7]);
        $this->actingAs($admin)
            ->post('/kebutuhan-sementara/persetujuan', ['instansi_id' => $instansi->id, 'aksi' => 'tolak']);
        $this->assertSame(0, KebutuhanSementara::count());
    }

    public function test_impor_sel_jumlah_kosong_dilaporkan_dengan_jelas(): void
    {
        $instansi = $this->instansi();
        $this->unit($instansi, 'UK-1');
        $this->jabatan('JF-1');
        $path = sys_get_temp_dir().'/kosong.csv';
        file_put_contents($path, "kode_unit,kode_jabatan,jumlah,dasar\nUK-1,JF-1,,x\n");

        $import = new KebutuhanSementaraImport($instansi->id, null);
        Excel::import($import, new UploadedFile($path, 'kosong.csv', null, null, true));

        $this->assertStringContainsString('jumlah kosong', $import->errors[0]);
        $this->assertSame(0, KebutuhanSementara::count());
    }

    public function test_laporan_sementara_vs_abk(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        [$a, $b] = [$this->jabatan('A'), $this->jabatan('B')];
        KebutuhanSementara::create(['instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $a->id, 'jumlah' => 4]);
        KebutuhanSementara::create(['instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $b->id, 'jumlah' => 2]);
        $this->abk($unit, $a, 6);

        $hasil = app(LaporanService::class)->buat('sementara_vs_abk', ['instansi_id' => $instansi->id]);

        $barisA = collect($hasil['baris'])->firstWhere(2, 'Jabatan A');
        $barisB = collect($hasil['baris'])->firstWhere(2, 'Jabatan B');
        $this->assertSame([4, 6, 2, 'ABK final menggantikan'], [$barisA[3], $barisA[4], $barisA[5], $barisA[8]]);
        $this->assertSame(['-', 'Masih sementara'], [$barisB[4], $barisB[8]]);
        $this->assertSame(['posisi' => 2, 'sudah_abk' => 1, 'masih_sementara' => 1], $hasil['angka']);
    }
}
