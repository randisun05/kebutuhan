<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Sumber pembanding bawaan untuk BKN: ABK dan pegawai SIMONKEB (dihitung langsung) serta bezetting dari API Dashboard (ditarik berkala). */
    public function up(): void
    {
        $bkn = DB::table('instansi_pembina')->where('kode', 'bkn')->value('id');
        if (! $bkn) {
            return;
        }
        $now = now();
        $sumber = [
            ['abk_simonkeb', 'ABK SIMONKEB', 'kebutuhan', 'unit', 'abk', 4, true, 'ABK final terbaru per unit kerja, dijumlahkan per instansi; memerlukan kaitan jabatan ke JF MASN (form Jabatan).'],
            ['pegawai_simonkeb', 'Pegawai aktif SIMONKEB', 'bezetting', 'unit', 'pegawai', 5, true, 'Pegawai aktif di SIMONKEB (kondisi kini), per jabatan yang dikaitkan ke JF MASN.'],
            ['bezetting_dashboard', 'Bezetting API Dashboard', 'bezetting', 'instansi', 'dashboard', 6, false, 'Data JF dari API Dashboard (kondisi kini). Aktifkan setelah DASHBOARD_JF_* terisi; lihat docs/API-DASHBOARD.md.'],
        ];
        foreach ($sumber as [$kode, $nama, $jenis, $tingkat, $adapter, $urutan, $aktif, $ket]) {
            if (DB::table('sumber_data')->where('pembina_id', $bkn)->where('kode', $kode)->doesntExist()) {
                DB::table('sumber_data')->insert([
                    'pembina_id' => $bkn, 'kode' => $kode, 'nama' => $nama, 'jenis' => $jenis, 'tingkat' => $tingkat, 'sifat' => 'hidup', 'adapter' => $adapter,
                    'urutan' => $urutan, 'is_active' => $aktif, 'keterangan' => $ket, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('sumber_data')->whereIn('kode', ['abk_simonkeb', 'pegawai_simonkeb', 'bezetting_dashboard'])->delete();
    }
};
