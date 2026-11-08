<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class FormUnitKerjaTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    public function test_ubah_unit_tanpa_parent_id_tidak_error(): void
    {
        $instansi = $this->instansi();
        $unit = $this->unit($instansi, 'Biro Umum');

        $this->actingAs($this->user(Role::Admin))
            ->put("/unit-kerja/{$unit->id}", ['instansi_id' => $instansi->id, 'nama' => 'Biro Umum Baru'])
            ->assertRedirect();

        $this->assertSame('Biro Umum Baru', $unit->fresh()->nama);
    }

    public function test_pesan_validasi_berbahasa_indonesia(): void
    {
        $this->actingAs($this->user(Role::Admin))
            ->post('/unit-kerja', ['instansi_id' => 0, 'nama' => ''])
            ->assertSessionHasErrors(['instansi_id' => 'Instansi yang dipilih tidak valid.', 'nama' => 'Nama wajib diisi.']);
    }
}
