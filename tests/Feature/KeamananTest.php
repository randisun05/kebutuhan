<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Pegawai;
use App\Models\UnitKerja;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class KeamananTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    public function test_halaman_login_lupa_password_dan_akun(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/forgot-password')->assertOk();
        $this->actingAs($this->user(Role::Admin))->get('/akun')->assertOk();
    }

    public function test_peran_wajib_2fa_diarahkan_ke_halaman_akun(): void
    {
        config(['simonkeb.wajib_2fa' => ['admin']]);
        $admin = $this->user(Role::Admin);

        $this->actingAs($admin)->get('/dashboard')->assertRedirect('/akun');
        $this->get('/akun')->assertOk();

        $admin->forceFill(['two_factor_secret' => encrypt('x'), 'two_factor_confirmed_at' => now()])->save();
        $this->get('/dashboard')->assertOk();

        // peran lain tidak terdampak
        $this->actingAs($this->user(Role::Pimpinan))->get('/dashboard')->assertOk();
    }

    public function test_login_dengan_2fa_meminta_kode(): void
    {
        $admin = $this->user(Role::Admin);
        $admin->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    public function test_log_audit_mencatat_perubahan_dan_hanya_untuk_admin(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'U');
        $jab = $this->jabatan('JF');
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $this->pegawai($unit, $jab, 1);
        $pegawai = Pegawai::first();

        $this->actingAs($operator)->put("/pegawai/{$pegawai->id}", [
            'instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $jab->id, 'nip' => $pegawai->nip,
            'nama' => 'Nama Baru', 'status_kepegawaian' => 'pns', 'is_active' => true, 'keterangan_mutasi' => 'SK 1',
        ])->assertSessionHasNoErrors();

        $this->get('/audit')->assertForbidden();
        $this->actingAs($this->user(Role::Admin))->get('/audit?q=Nama%20Baru')->assertOk()
            ->assertInertia(fn ($page) => $page->where('datas.data.0.causer', $operator->name));
    }

    public function test_pegawai_riwayat_mutasi_tercatat_dengan_keterangan(): void
    {
        $instansi = $this->instansi();
        $a = $this->unit($instansi, 'A');
        $b = $this->unit($instansi, 'B');
        $jab = $this->jabatan('JF');
        $this->pegawai($a, $jab, 1);
        $pegawai = Pegawai::first();

        $this->actingAs($this->user(Role::OperatorInstansi, $instansi))->put("/pegawai/{$pegawai->id}", [
            'instansi_id' => $instansi->id, 'unit_kerja_id' => $b->id, 'jabatan_id' => $jab->id, 'nip' => $pegawai->nip,
            'nama' => $pegawai->nama, 'status_kepegawaian' => 'pns', 'is_active' => true, 'keterangan_mutasi' => 'SK Mutasi 12/2026',
        ])->assertSessionHasNoErrors();

        $riwayat = $pegawai->riwayats()->first();
        $this->assertSame('mutasi_unit', $riwayat->jenis);
        $this->assertSame($a->id, $riwayat->dari_unit_id);
        $this->assertSame($b->id, $riwayat->ke_unit_id);
        $this->assertSame('SK Mutasi 12/2026', $riwayat->keterangan);
    }

    public function test_impor_unit_kerja_membentuk_hierarki(): void
    {
        $instansi = $this->instansi();
        $csv = "kode,nama,kode_induk,eselon,nama_jabatan_pimpinan,siasn_unor_id\n"
            ."B,Bagian Organisasi,A,III,Kabag Organisasi,\n"
            ."A,Sekretariat Daerah,,II,Sekda,U-1\n";
        $file = UploadedFile::fake()->createWithContent('unit.csv', $csv);

        $this->actingAs($this->user(Role::OperatorInstansi, $instansi))
            ->post('/unit-kerja/import', ['instansi_id' => $instansi->id, 'file' => $file])->assertRedirect();

        $b = UnitKerja::where('kode', 'B')->firstOrFail();
        $this->assertSame(UnitKerja::where('kode', 'A')->value('id'), $b->parent_id);
        $this->assertSame('U-1', UnitKerja::where('kode', 'A')->value('siasn_unor_id'));
    }
}
