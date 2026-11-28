<?php

namespace Database\Seeders;

use App\Models\ImporProfil;
use App\Models\InstansiAlias;
use App\Models\InstansiPembina;
use App\Models\JfJabatanPokok;
use App\Models\JfJenjang;
use App\Models\RefInstansiBkn;
use App\Models\SumberData;
use App\Services\RekomendasiJfImportService;
use Illuminate\Database\Seeder;
use Throwable;

/**
 * Data awal (starter) untuk produksi, khusus instansi pembina BKN (JF SDM Aparatur). AMAN dijalankan di produksi dan
 * berulang kali: tidak membuat akun demo, tidak menimpa perubahan admin (hanya melengkapi yang belum ada), dan impor
 * angka bersifat idempoten (angka berversi, tidak menggandakan).
 *
 *   php artisan db:seed --class=BknStarterSeeder
 *
 * Langkah: (1) master BKN (pembina, jabatan pokok, jenjang, sumber, profil impor), (2) referensi instansi BKN,
 * (3) alias bawaan, (4) angka rekomendasi BKN, penetapan KemenPANRB, dan bezetting saat rekomendasi dari Excel rekap tim data,
 * (5) opsional: salin data eksisting JF dari database ujikom-pusbin. Berbeda dengan DatabaseSeeder (data demo).
 */
class BknStarterSeeder extends Seeder
{
    /** Nama di Excel rekap yang berbeda penulisannya dari referensi BKN → siasn_id referensi. Periksa di menu Alias Instansi. */
    private const ALIAS_BAWAAN = [
        'Pemerintah Kab. Gunungkidul' => 'A5EB03E23B42F6A0E040640A040252AD',        // "Pemerintah Kab. Gunung Kidul"
        'Pemerintah Kab. Kepulauan Mentawai' => 'A5EB03E23CB0F6A0E040640A040252AD', // "Pemerintah Kab. Kep. Mentawai"
        'Kejaksaan Republik Indonesia' => 'A5EB03E23BFEF6A0E040640A040252AD',       // "Kejaksaan Agung"
    ];

    public function run(): void
    {
        $this->masterBkn();
        $this->referensiInstansi();
        $this->aliasBawaan();
        $this->rekap();
        $this->eksistingJf();
    }

    /** Lengkapi master BKN yang belum ada. Yang sudah ada (termasuk yang diubah admin) tidak disentuh. */
    private function masterBkn(): void
    {
        $bkn = InstansiPembina::firstOrCreate(['kode' => 'bkn'], ['nama' => 'Badan Kepegawaian Negara', 'is_active' => true, 'keterangan' => 'Pembina JF SDM Aparatur']);

        $jabatan = [['analis', 'Analis SDM Aparatur', 'keahlian', 1], ['pranata', 'Pranata SDM Aparatur', 'keterampilan', 2],
            ['asesor', 'Asesor SDM Aparatur', 'keahlian', 3], ['auditor', 'Auditor Manajemen ASN', 'keahlian', 4]];
        foreach ($jabatan as [$kode, $nama, $rumpun, $urutan]) {
            JfJabatanPokok::firstOrCreate(['kode' => $kode], ['pembina_id' => $bkn->id, 'nama' => $nama, 'rumpun' => $rumpun, 'urutan' => $urutan, 'is_active' => true]);
        }

        $jenjang = [['pertama', 'Pertama', 'keahlian', 1], ['muda', 'Muda', 'keahlian', 2], ['madya', 'Madya', 'keahlian', 3], ['utama', 'Utama', 'keahlian', 4],
            ['terampil', 'Terampil', 'keterampilan', 1], ['mahir', 'Mahir', 'keterampilan', 2], ['penyelia', 'Penyelia', 'keterampilan', 3]];
        foreach ($jenjang as [$kode, $nama, $rumpun, $urutan]) {
            JfJenjang::firstOrCreate(['kode' => $kode], ['nama' => $nama, 'rumpun' => $rumpun, 'urutan' => $urutan, 'is_active' => true]);
        }

        // jenjang per jabatan hanya diisi bila jabatan belum punya satu pun (agar pengaturan admin tidak ditimpa)
        $pasang = ['analis' => ['pertama', 'muda', 'madya', 'utama'], 'asesor' => ['pertama', 'muda', 'madya', 'utama'], 'auditor' => ['pertama', 'muda', 'madya', 'utama'],
            'pranata' => ['terampil', 'mahir', 'penyelia']];
        foreach ($pasang as $kodeJab => $daftar) {
            $jab = JfJabatanPokok::where('kode', $kodeJab)->first();
            if ($jab && ! $jab->jenjangs()->exists()) {
                foreach ($daftar as $i => $kodeJen) {
                    $jab->jenjangs()->attach(JfJenjang::where('kode', $kodeJen)->value('id'), ['urutan_kolom' => $i + 1]);
                }
            }
        }

        $sumber = [
            ['bezetting_rekom', 'Bezetting saat rekomendasi', 'bezetting', 1, 'Jumlah pejabat pada saat rekomendasi disusun (foto, bukan kondisi kini).'],
            ['rekom_bkn', 'Rekomendasi BKN', 'kebutuhan', 2, 'Kolom "Perhitungan formasi" pada Excel rekap.'],
            ['penetapan_menpan', 'Penetapan KemenPANRB', 'kebutuhan', 3, null],
        ];
        foreach ($sumber as [$kode, $nama, $jenis, $urutan, $ket]) {
            SumberData::firstOrCreate(['pembina_id' => $bkn->id, 'kode' => $kode], [
                'nama' => $nama, 'jenis' => $jenis, 'tingkat' => 'instansi', 'sifat' => 'foto', 'adapter' => 'fakta', 'urutan' => $urutan, 'is_active' => true, 'keterangan' => $ket,
            ]);
        }

        // sumber pembanding: ABK dan pegawai dihitung langsung dari SIMONKEB; Dashboard ditarik berkala (nonaktif sampai dikonfigurasi)
        $pembanding = [
            ['abk_simonkeb', 'ABK SIMONKEB', 'kebutuhan', 'unit', 'abk', 4, true, 'ABK final terbaru per unit kerja, dijumlahkan per instansi; memerlukan kaitan jabatan ke JF MASN (form Jabatan).'],
            ['pegawai_simonkeb', 'Pegawai aktif SIMONKEB', 'bezetting', 'unit', 'pegawai', 5, true, 'Pegawai aktif di SIMONKEB (kondisi kini), per jabatan yang dikaitkan ke JF MASN.'],
            ['bezetting_dashboard', 'Bezetting API Dashboard', 'bezetting', 'instansi', 'dashboard', 6, false, 'Data JF dari API Dashboard (kondisi kini). Aktifkan setelah DASHBOARD_JF_* terisi; lihat docs/API-DASHBOARD.md.'],
        ];
        foreach ($pembanding as [$kode, $nama, $jenis, $tingkat, $adapter, $urutan, $aktif, $ket]) {
            SumberData::firstOrCreate(['pembina_id' => $bkn->id, 'kode' => $kode], [
                'nama' => $nama, 'jenis' => $jenis, 'tingkat' => $tingkat, 'sifat' => 'hidup', 'adapter' => $adapter, 'urutan' => $urutan, 'is_active' => $aktif, 'keterangan' => $ket,
            ]);
        }

        ImporProfil::firstOrCreate(['kode' => 'rekap-jfmasn'], [
            'pembina_id' => $bkn->id, 'nama' => 'Rekap Kebutuhan JFMASN (tim data)', 'sheet' => 'Input Data (Instansi Baru)', 'is_active' => true,
            'pemetaan' => [
                'kolom' => ['instansi' => 'D', 'jabatan' => 'E', 'jenis_instansi' => 'F', 'pokja' => 'G', 'status_rekom' => 'H', 'jenis_rekom' => 'O',
                    'wilayah_kerja' => 'AX', 'sesuai_bezetting' => 'AF', 'catatan' => ['AG', 'AY']],
                'surat' => [
                    ['jenis' => 'rekomendasi', 'nomor' => 'J', 'tanggal' => 'K', 'tautan' => 'L'],
                    ['jenis' => 'pengembalian', 'nomor' => 'M', 'tanggal' => 'N'],
                    ['jenis' => 'penetapan_menpan', 'nomor' => 'AP', 'tanggal' => 'AQ', 'ttd_jabatan' => 'AR', 'ttd_nama' => 'AS'],
                ],
                'blok' => [
                    ['sumber' => 'bezetting_rekom', 'kolom_awal' => 'R'],
                    ['sumber' => 'rekom_bkn', 'kolom_awal' => 'W'],
                    ['sumber' => 'penetapan_menpan', 'kolom_awal' => 'AL', 'cadangan_kolom_awal' => 'AH'],
                ],
            ],
        ]);

        $this->info('Master BKN lengkap: '.JfJabatanPokok::where('pembina_id', $bkn->id)->count().' jabatan pokok, '.SumberData::where('pembina_id', $bkn->id)->count().' sumber angka, 1 profil impor.');
    }

    /** Referensi instansi BKN dari CSV (memakai perintah yang sudah ada, idempoten). */
    private function referensiInstansi(): void
    {
        $this->command?->callSilent('jf:impor-ref');
        $this->info('Referensi instansi BKN: '.RefInstansiBkn::count().' entri.');
    }

    private function aliasBawaan(): void
    {
        $dibuat = 0;
        foreach (self::ALIAS_BAWAAN as $nama => $siasnId) {
            $refId = RefInstansiBkn::where('siasn_id', $siasnId)->value('id');
            if (! $refId) {
                continue;
            }
            $alias = InstansiAlias::firstOrCreate(['nama_normal' => InstansiAlias::normalisasi($nama)], ['nama' => $nama, 'ref_instansi_id' => $refId, 'asal' => 'manual']);
            $dibuat += $alias->wasRecentlyCreated ? 1 : 0;
        }
        $this->info("Alias instansi bawaan: {$dibuat} baru (periksa di menu Alias Instansi).");
    }

    /** Rekomendasi BKN, penetapan KemenPANRB, dan bezetting saat rekomendasi dari Excel rekap tim data. */
    private function rekap(): void
    {
        if (! config('simonkeb.starter.impor_rekap')) {
            $this->info('Impor Excel rekap dilewati (STARTER_IMPOR_REKAP=false).');

            return;
        }
        $path = config('simonkeb.starter.rekap_path');
        if (! is_file($path)) {
            $this->warn("Berkas rekap tidak ditemukan: {$path}. Isi REKAP_JFMASN_PATH atau unggah lewat menu Rekomendasi JF MASN.");

            return;
        }

        $profil = ImporProfil::where('kode', 'rekap-jfmasn')->firstOrFail();
        $svc = app(RekomendasiJfImportService::class);
        try {
            $hasil = $svc->baca($path, $profil);
        } catch (Throwable $e) {
            $this->warn('Excel rekap tidak dapat dibaca: '.$e->getMessage());

            return;
        }
        $impor = $svc->simpan($hasil, null, basename($path));
        $angka = collect($hasil['ringkas']['angka_per_sumber'])->map(fn ($v, $k) => "{$k} ".number_format($v, 0, ',', '.'))->implode(', ');
        $this->info("Rekap Excel: {$impor->dibuat} baris baru, {$impor->diperbarui} diperbarui, {$impor->fakta_baru} angka baru/berubah ({$angka}); {$hasil['ringkas']['jumlah_tidak_cocok']} instansi belum dipetakan (menu Alias Instansi).");
    }

    /** Data eksisting JF (pegawai) dari database ujikom-pusbin: hanya bila diminta, karena menyalin data dalam jumlah besar. */
    private function eksistingJf(): void
    {
        if (! config('simonkeb.starter.impor_ujikom')) {
            $this->info('Data eksisting JF (pegawai) dilewati. Untuk menyalin dari ujikom-pusbin: isi UJIKOM_DB_* lalu STARTER_IMPOR_UJIKOM=true, atau jalankan php artisan jf:impor-ujikom / jf:sync.');

            return;
        }
        $this->command?->call('jf:impor-ujikom');
    }

    private function info(string $pesan): void
    {
        $this->command?->info($pesan);
    }

    private function warn(string $pesan): void
    {
        $this->command?->warn($pesan);
    }
}
