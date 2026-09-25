<?php

namespace Tests\Feature\Concerns;

use App\Enums\Role;
use App\Models\AnjabAbk;
use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitKerja;
use App\Models\User;

trait BuildsKebutuhanData
{
    private int $nipSeq = 0;

    protected function instansi(string $kode = 'INS'): Instansi
    {
        return Instansi::create(['kode' => $kode, 'nama' => 'Instansi '.$kode, 'jenis' => 'kabupaten']);
    }

    protected function unit(Instansi $instansi, string $nama, ?UnitKerja $parent = null): UnitKerja
    {
        return UnitKerja::create(['instansi_id' => $instansi->id, 'parent_id' => $parent?->id, 'nama' => $nama, 'kode' => $nama]);
    }

    protected function jabatan(string $kode, string $jenis = 'fungsional', int $bup = 58): Jabatan
    {
        return Jabatan::create(['kode' => $kode, 'nama' => 'Jabatan '.$kode, 'jenis' => $jenis, 'bup' => $bup]);
    }

    protected function abk(UnitKerja $unit, Jabatan $jabatan, int $kebutuhan, string $status = 'final'): AnjabAbk
    {
        $anjab = AnjabAbk::create([
            'instansi_id' => $unit->instansi_id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $jabatan->id,
            'tahun' => 2026, 'status' => $status,
        ]);
        // satu uraian tugas yang menghasilkan tepat $kebutuhan pegawai
        $anjab->uraianTugas()->create(['uraian_tugas' => 'Tugas', 'volume' => 75000 * $kebutuhan / 60, 'norma_waktu' => 60]);
        $anjab->recalculate();

        return $anjab;
    }

    protected function pegawai(UnitKerja $unit, Jabatan $jabatan, int $jumlah = 1, ?string $tanggalLahir = '1990-01-01'): void
    {
        for ($i = 0; $i < $jumlah; $i++) {
            Pegawai::create([
                'instansi_id' => $unit->instansi_id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $jabatan->id,
                'nip' => str_pad((string) ++$this->nipSeq, 18, '1', STR_PAD_LEFT), 'nama' => 'Pegawai '.$this->nipSeq,
                'tanggal_lahir' => $tanggalLahir,
            ]);
        }
    }

    protected function user(Role $role, ?Instansi $instansi = null): User
    {
        return User::create([
            'name' => $role->label(), 'email' => $role->value.($instansi?->id ?? '').'@test.local',
            'password' => 'password', 'role' => $role, 'instansi_id' => $instansi?->id,
        ]);
    }
}
