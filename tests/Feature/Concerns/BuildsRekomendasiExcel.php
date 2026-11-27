<?php

namespace Tests\Feature\Concerns;

use App\Enums\Role;
use App\Models\ImporProfil;
use App\Models\InstansiPembina;
use App\Models\JfJabatanPokok;
use App\Models\JfJenjang;
use App\Models\KebutuhanFakta;
use App\Models\RekomendasiJf;
use App\Models\SumberData;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Pembantu uji modul Rekomendasi JF MASN: membuat Excel uji, membaca fakta, dan pengguna pembina. */
trait BuildsRekomendasiExcel
{
    /** Buat berkas xlsx dengan tata letak seperti sheet "Input Data (Instansi Baru)". */
    protected function buatExcel(array $baris, ?string $sheet = null): string
    {
        $sheet ??= ImporProfil::firstOrFail()->sheet; // nama sheet berasal dari profil impor, bukan konstanta kode
        $wb = new Spreadsheet;
        $ws = $wb->getActiveSheet()->setTitle($sheet);
        $ws->fromArray(['NO', 'x', 'x', 'NAMA INSTANSI', 'JABATAN'], null, 'A2'); // baris judul
        $ws->fromArray([null, null, 1.0, 2.0, 3.0], null, 'A3');                    // baris nomor kolom
        $r = 4;
        foreach ($baris as $b) {
            $row = array_fill(0, 61, null);
            foreach ($b as $kolom => $nilai) {
                $row[$kolom] = $nilai;
            }
            $ws->fromArray($row, null, 'A'.$r++);
        }
        $path = sys_get_temp_dir().'/rekom-'.uniqid().'.xlsx';
        (new Xlsx($wb))->save($path);

        return $path;
    }

    /** Nilai terbaru satu sumber × jenjang untuk satu baris rekomendasi. */
    protected function fakta(RekomendasiJf $r, string $sumber, string $jenjang): ?int
    {
        return KebutuhanFakta::where('rekomendasi_jf_id', $r->id)->where('is_terbaru', true)
            ->whereHas('sumber', fn ($q) => $q->where('kode', $sumber))->whereHas('jenjang', fn ($q) => $q->where('kode', $jenjang))->value('nilai');
    }

    protected function baris(string $instansi, string $jabatan, array $lebih = []): array
    {
        return $lebih + [3 => $instansi, 4 => $jabatan, 5 => 'KAB', 6 => 'POKJA 2', 7 => 'DONE', 14 => 'Rekom baru',
            17 => 2, 18 => 3, 19 => 1, 20 => 0, 22 => 16, 23 => 12, 24 => 9, 25 => 1, 37 => 16, 38 => 10, 39 => 9, 40 => null,
            41 => 'B/3800/M.SM.01.00/2025', 42 => 45900, 43 => 'Sekretaris Kementerian', 49 => 'BKN Pusat', 50 => 'Instansi baru'];
    }

    protected function pembinaBkn(): InstansiPembina
    {
        return InstansiPembina::where('kode', 'bkn')->firstOrFail();
    }

    /** Pembina kedua beserta satu jabatan pokok, jenjang, sumber, dan profil impor miliknya sendiri (tata letak kolom sama). */
    protected function pembinaKedua(string $kode = 'lan'): InstansiPembina
    {
        $p = InstansiPembina::create(['kode' => $kode, 'nama' => 'Pembina '.strtoupper($kode)]);
        $jab = JfJabatanPokok::create(['pembina_id' => $p->id, 'kode' => 'jf-'.$kode, 'nama' => 'JF Milik '.strtoupper($kode), 'rumpun' => 'keahlian']);
        $jab->jenjangs()->attach(JfJenjang::whereIn('kode', ['pertama', 'muda'])->pluck('id', 'kode')->mapWithKeys(fn ($id, $k) => [$id => ['urutan_kolom' => $k === 'pertama' ? 1 : 2]])->all());
        SumberData::create(['pembina_id' => $p->id, 'kode' => 'rekom_'.$kode, 'nama' => 'Rekomendasi '.strtoupper($kode), 'jenis' => 'kebutuhan', 'tingkat' => 'instansi', 'sifat' => 'foto', 'adapter' => 'fakta', 'urutan' => 1]);
        ImporProfil::create(['pembina_id' => $p->id, 'kode' => 'profil-'.$kode, 'nama' => 'Profil '.strtoupper($kode), 'sheet' => 'Data '.strtoupper($kode), 'pemetaan' => [
            'kolom' => ['instansi' => 'D', 'jabatan' => 'E', 'status_rekom' => 'H'], 'surat' => [], 'blok' => [['sumber' => 'rekom_'.$kode, 'kolom_awal' => 'W', 'lebar' => 4]],
        ]]);

        return $p;
    }

    protected function penggunaPembina(InstansiPembina $p, ?string $email = null): User
    {
        return User::create(['name' => 'Pembina '.$p->kode, 'email' => $email ?? 'pembina-'.$p->kode.'-'.uniqid().'@test.local', 'password' => 'password', 'role' => Role::Pembina, 'pembina_id' => $p->id]);
    }
}
