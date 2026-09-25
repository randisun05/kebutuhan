<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UsulanStatus;
use App\Models\Penetapan;
use App\Models\Usulan;
use App\Services\MonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class UsulanWorkflowTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    public function test_alur_lengkap_dari_pengajuan_sampai_penetapan(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $this->abk($unit, $jab, 5);
        $this->pegawai($unit, $jab, 2);

        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $bkn = $this->user(Role::VerifikatorBkn);
        $kemenpan = $this->user(Role::ValidatorKemenpan);

        // 1. instansi membuat usulan dan menarik rincian dari ABK
        $this->actingAs($operator)->post('/usulan', [
            'instansi_id' => $instansi->id, 'tahun' => (int) now()->format('Y') + 1, 'jenis_asn' => 'pns', 'perihal' => 'Usulan',
        ])->assertRedirect();
        $usulan = Usulan::firstOrFail();
        $this->post("/usulan/{$usulan->id}/tarik-abk")->assertRedirect();
        $detail = $usulan->details()->firstOrFail();
        $this->assertSame(3, $detail->jumlah_usul);

        // 2. operator tidak boleh memverifikasi usulannya sendiri
        $this->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'mulai_verifikasi'])->assertSessionHasErrors('aksi');

        $this->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'ajukan'])->assertSessionHasNoErrors();
        $this->assertSame(UsulanStatus::Diajukan, $usulan->fresh()->status);

        // usulan yang sudah diajukan tidak dapat diubah instansi
        $this->put("/usulan/{$usulan->id}/details", ['details' => [['id' => $detail->id, 'jumlah_usul' => 9]]])->assertForbidden();

        // 3. BKN: rekomendasi tidak boleh melebihi usulan
        $this->actingAs($bkn)->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'mulai_verifikasi'])->assertSessionHasNoErrors();
        $this->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'rekomendasi', 'details' => [$detail->id => 4]])
            ->assertSessionHasErrors("details.{$detail->id}");
        $this->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'rekomendasi', 'details' => [$detail->id => 2]])->assertSessionHasNoErrors();
        $this->assertSame(UsulanStatus::PertimbanganTeknis, $usulan->fresh()->status);

        // BKN tidak boleh menetapkan
        $this->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'mulai_validasi'])->assertSessionHasErrors('aksi');

        // 4. KemenPANRB memvalidasi & menetapkan
        $this->actingAs($kemenpan)->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'mulai_validasi'])->assertSessionHasNoErrors();
        $this->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'tetapkan', 'details' => [$detail->id => 2]])->assertSessionHasErrors('nomor_sk');
        $this->post("/usulan/{$usulan->id}/aksi", [
            'aksi' => 'tetapkan', 'details' => [$detail->id => 2], 'nomor_sk' => 'SK/1/2026', 'tanggal_sk' => '2026-09-01',
        ])->assertSessionHasNoErrors();

        $usulan->refresh();
        $this->assertSame(UsulanStatus::Ditetapkan, $usulan->status);
        $this->assertSame(2, Penetapan::firstOrFail()->total_ditetapkan);
        $this->assertSame(6, $usulan->logs()->count()); // buat + 5 transisi

        // 5. formasi yang ditetapkan tampil di monitoring
        $this->assertSame(2, app(MonitoringService::class)->summary(['instansi_id' => $instansi->id])['formasi']);
    }

    public function test_kembalikan_wajib_catatan_dan_bisa_diajukan_ulang(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $bkn = $this->user(Role::VerifikatorBkn);

        $usulan = Usulan::create(['instansi_id' => $instansi->id, 'tahun' => 2027, 'perihal' => 'X', 'status' => UsulanStatus::Diajukan]);
        $usulan->details()->create(['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah_usul' => 2]);

        $this->actingAs($bkn)->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'kembalikan'])->assertSessionHasErrors('catatan');
        $this->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'kembalikan', 'catatan' => 'Lengkapi surat'])->assertSessionHasNoErrors();
        $this->assertSame(UsulanStatus::Dikembalikan, $usulan->fresh()->status);

        $this->actingAs($operator)->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'ajukan'])->assertSessionHasNoErrors();
        $this->assertSame(UsulanStatus::Diajukan, $usulan->fresh()->status);
    }

    public function test_usulan_tanpa_rincian_tidak_dapat_diajukan(): void
    {
        $instansi = $this->instansi();
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $usulan = Usulan::create(['instansi_id' => $instansi->id, 'tahun' => 2027, 'perihal' => 'X', 'status' => UsulanStatus::Draft]);

        $this->actingAs($operator)->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'ajukan'])->assertSessionHasErrors('details');
    }

    public function test_operator_tidak_dapat_mengakses_usulan_instansi_lain(): void
    {
        $a = $this->instansi('A');
        $b = $this->instansi('B');
        $operatorB = $this->user(Role::OperatorInstansi, $b);
        $usulan = Usulan::create(['instansi_id' => $a->id, 'tahun' => 2027, 'perihal' => 'X', 'status' => UsulanStatus::Draft]);

        $this->actingAs($operatorB)->get("/usulan/{$usulan->id}")->assertForbidden();
        $this->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'ajukan'])->assertForbidden();
        $this->get("/monitoring/instansi/{$a->id}")->assertForbidden();
    }
}
