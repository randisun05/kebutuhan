<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_dan_user_nonaktif_ditolak(): void
    {
        $user = $this->user(Role::Admin);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');

        auth()->logout();
        $user->update(['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_halaman_utama_dapat_dibuka_semua_peran(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $this->abk($unit, $jab, 2);
        $this->pegawai($unit, $jab, 1);

        foreach (Role::cases() as $role) {
            $user = $this->user($role, $role === Role::OperatorInstansi ? $instansi : null);
            $this->actingAs($user);
            $this->get('/dashboard')->assertOk();
            $this->get('/usulan')->assertOk();
            $this->get('/anjab')->assertOk();
            $this->get('/penetapan')->assertOk();
            $this->get("/monitoring/unit/{$unit->id}")->assertOk();
            $this->get("/monitoring/instansi/{$instansi->id}")->assertOk();
        }
    }

    public function test_menu_referensi_hanya_untuk_admin(): void
    {
        $instansi = $this->instansi();
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $bkn = $this->user(Role::VerifikatorBkn);

        $this->actingAs($operator)->get('/instansi')->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->get('/pegawai')->assertOk();
        $this->get('/monitoring')->assertRedirect("/monitoring/instansi/{$instansi->id}");

        $this->actingAs($bkn)->get('/pegawai')->assertForbidden();
        $this->get('/monitoring')->assertOk();
        $this->get('/monitoring/export')->assertOk();
    }

    public function test_hanya_admin_dan_operator_yang_dapat_mengubah_abk(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $anjab = $this->abk($unit, $jab, 2);

        $this->actingAs($this->user(Role::VerifikatorBkn))->get("/anjab/{$anjab->id}/edit")->assertForbidden();

        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $this->actingAs($operator)->put("/anjab/{$anjab->id}", [
            'instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'tahun' => 2026,
            'waktu_kerja_efektif' => 75000, 'status' => 'final',
            'uraian' => [['uraian_tugas' => 'A', 'volume' => 1000, 'norma_waktu' => 120]], // 120.000 menit = 1,6 -> 2
        ])->assertRedirect();

        $anjab->refresh();
        $this->assertSame(2, $anjab->kebutuhan);
        $this->assertEquals(1.6, $anjab->kebutuhan_hitung);
    }
}
