<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UsulanStatus;
use App\Models\Usulan;
use App\Notifications\UsulanDiproses;
use App\Services\MonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class UsulanLanjutanTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    private function usulanDitetapkan($instansi, $unit, $jab, int $ditetapkan): Usulan
    {
        $usulan = Usulan::create(['instansi_id' => $instansi->id, 'tahun' => 2027, 'perihal' => 'X', 'status' => UsulanStatus::Ditetapkan]);
        $usulan->details()->create(['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah_usul' => $ditetapkan,
            'jumlah_rekomendasi' => $ditetapkan, 'jumlah_ditetapkan' => $ditetapkan]);
        $usulan->penetapan()->create(['instansi_id' => $instansi->id, 'nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-09-01', 'tahun' => 2027, 'total_ditetapkan' => $ditetapkan]);

        return $usulan;
    }

    public function test_pengisian_formasi_mengurangi_sisa_di_monitoring(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $usulan = $this->usulanDitetapkan($instansi, $unit, $jab, 5);
        $detail = $usulan->details()->first();
        $operator = $this->user(Role::OperatorInstansi, $instansi);

        $this->actingAs($operator)->get('/formasi')->assertOk();
        $this->put('/formasi', ['items' => [['id' => $detail->id, 'jumlah_terisi' => 6]]])->assertSessionHasErrors('items');
        $this->put('/formasi', ['items' => [['id' => $detail->id, 'jumlah_terisi' => 3]]])->assertSessionHasNoErrors();

        $this->assertSame(2, app(MonitoringService::class)->summary(['instansi_id' => $instansi->id])['formasi']);

        $this->actingAs($this->user(Role::VerifikatorBkn))->put('/formasi', ['items' => [['id' => $detail->id, 'jumlah_terisi' => 5]]])->assertForbidden();
    }

    public function test_pdf_penetapan_dapat_diunduh(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $usulan = $this->usulanDitetapkan($instansi, $unit, $jab, 2);

        $this->actingAs($this->user(Role::Admin))
            ->get("/penetapan/{$usulan->penetapan->id}/pdf")
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_notifikasi_dikirim_ke_pihak_yang_perlu_bertindak(): void
    {
        Notification::fake();
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $bkn = $this->user(Role::VerifikatorBkn);
        $kemenpan = $this->user(Role::ValidatorKemenpan);

        $usulan = Usulan::create(['instansi_id' => $instansi->id, 'tahun' => 2027, 'perihal' => 'X', 'status' => UsulanStatus::Draft]);
        $usulan->details()->create(['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah_usul' => 2, 'prioritas' => 1]);

        $this->actingAs($operator)->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'ajukan']);
        Notification::assertSentTo($bkn, UsulanDiproses::class);
        Notification::assertNotSentTo([$kemenpan, $operator], UsulanDiproses::class);

        $this->actingAs($bkn)->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'kembalikan', 'catatan' => 'Lengkapi']);
        Notification::assertSentTo($operator, UsulanDiproses::class, fn ($n) => $n->catatan === 'Lengkapi');
    }

    public function test_notifikasi_tersimpan_dan_dapat_dibuka(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $bkn = $this->user(Role::VerifikatorBkn);
        $usulan = Usulan::create(['instansi_id' => $instansi->id, 'tahun' => 2027, 'perihal' => 'X', 'status' => UsulanStatus::Draft]);
        $usulan->details()->create(['unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'jumlah_usul' => 2]);

        $this->actingAs($operator)->post("/usulan/{$usulan->id}/aksi", ['aksi' => 'ajukan']);

        $notif = $bkn->notifications()->firstOrFail();
        $this->actingAs($bkn)->get('/notifikasi')->assertOk();
        $this->get("/notifikasi/{$notif->id}")->assertRedirect("/usulan/{$usulan->id}");
        $this->assertNotNull($notif->fresh()->read_at);
    }
}
