<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UsulanStatus;
use App\Models\AnjabAbk;
use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\Usulan;
use App\Models\UsulanDetail;
use App\Services\MonitoringService;
use App\Services\UsulanWorkflow;
use App\Support\Referensi;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

/**
 * Data demo agar alur hulu-hilir dan dashboard monitoring dapat langsung dicoba.
 * Semua akun demo memakai password: password
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // data demo tidak perlu masuk log audit
        activity()->disableLogging();
        mt_srand(2026);
        $faker = Faker::create('id_ID');
        $faker->seed(2026);

        $jabatans = $this->jabatans();

        $admin = User::create(['name' => 'Administrator', 'email' => 'admin@simonkeb.test', 'password' => 'password', 'role' => Role::Admin]);
        $bkn = User::create(['name' => 'Verifikator BKN', 'email' => 'bkn@simonkeb.test', 'password' => 'password', 'role' => Role::VerifikatorBkn]);
        $kemenpan = User::create(['name' => 'Validator KemenPANRB', 'email' => 'kemenpan@simonkeb.test', 'password' => 'password', 'role' => Role::ValidatorKemenpan]);
        User::create(['name' => 'Pimpinan', 'email' => 'pimpinan@simonkeb.test', 'password' => 'password', 'role' => Role::Pimpinan]);

        $instansis = [
            ['kode' => 'KAB-SJT', 'nama' => 'Pemerintah Kabupaten Sejahtera', 'jenis' => 'kabupaten', 'provinsi' => 'Jawa Barat'],
            ['kode' => 'KOT-MDN', 'nama' => 'Pemerintah Kota Madani', 'jenis' => 'kota', 'provinsi' => 'Jawa Tengah'],
            ['kode' => 'PROV-NSN', 'nama' => 'Pemerintah Provinsi Nusantara', 'jenis' => 'provinsi', 'provinsi' => 'Nusantara'],
            ['kode' => 'KL-ADM', 'nama' => 'Kementerian Administrasi Contoh', 'jenis' => 'pusat', 'provinsi' => 'DKI Jakarta'],
        ];

        $workflow = app(UsulanWorkflow::class);

        foreach ($instansis as $idx => $row) {
            $instansi = Instansi::create($row);
            $operator = User::create([
                'name' => 'Operator '.$row['kode'], 'email' => strtolower($row['kode']).'@simonkeb.test',
                'password' => 'password', 'role' => Role::OperatorInstansi, 'instansi_id' => $instansi->id,
            ]);

            $units = $this->units($instansi, $row['jenis']);
            $this->pegawaiDanAbk($instansi, $units, $jabatans, $faker, $operator, [0, -2, 1, 0][$idx]);

            // usulan contoh di berbagai tahap alur
            $targets = [
                0 => 'tetapkan', 1 => 'rekomendasi', 2 => 'ajukan', 3 => null,
            ];
            $usulan = Usulan::create([
                'instansi_id' => $instansi->id, 'tahun' => (int) now()->format('Y') + 1,
                'periode' => now()->format('Y').'-'.((int) now()->format('Y') + 4), 'jenis_asn' => $idx % 2 ? 'pppk' : 'pns',
                'perihal' => 'Usulan Kebutuhan ASN Tahun '.((int) now()->format('Y') + 1),
                'status' => UsulanStatus::Draft, 'created_by' => $operator->id,
            ]);
            $usulan->logs()->create(['user_id' => $operator->id, 'aksi' => 'buat', 'ke_status' => 'draft', 'catatan' => 'Usulan dibuat.']);
            $this->tarikAbk($usulan);

            $steps = ['ajukan' => $operator, 'mulai_verifikasi' => $bkn, 'rekomendasi' => $bkn, 'mulai_validasi' => $kemenpan, 'tetapkan' => $kemenpan];
            if ($target = $targets[$idx]) {
                foreach ($steps as $aksi => $actor) {
                    $payload = [];
                    if (in_array($aksi, ['rekomendasi', 'tetapkan'], true)) {
                        $field = $aksi === 'rekomendasi' ? 'jumlah_usul' : 'jumlah_rekomendasi';
                        $payload['details'] = $usulan->details()->get()
                            ->mapWithKeys(fn ($d) => [$d->id => (int) ceil($d->{$field} * 0.8)])->all();
                    }
                    if ($aksi === 'tetapkan') {
                        $payload += ['nomor_sk' => 'B/'.(100 + $idx).'/M.SM.01.00/'.now()->format('Y'), 'tanggal_sk' => now()->toDateString()];
                    }
                    $usulan = $workflow->transition($actor, $usulan->fresh(), $aksi, $payload + ['catatan' => $aksi === 'rekomendasi' ? 'Sesuai hasil ABK, disesuaikan dengan kemampuan anggaran.' : null]);
                    if ($aksi === $target) {
                        break;
                    }
                }
            }
        }

        // sebagian formasi yang sudah ditetapkan telah terisi hasil seleksi
        UsulanDetail::whereHas('usulan', fn ($q) => $q->where('status', UsulanStatus::Ditetapkan))
            ->each(fn ($d) => $d->update(['jumlah_terisi' => intdiv((int) $d->jumlah_ditetapkan, 2), 'terisi_updated_at' => now()]));

        // unit kerja: nama jabatan pimpinan
        UnitKerja::whereNull('nama_jabatan_pimpinan')->each(fn ($u) => $u->update([
            'nama_jabatan_pimpinan' => 'Kepala '.$u->nama,
        ]));

        activity()->enableLogging();
        $admin->touch();
    }

    private function tarikAbk(Usulan $usulan): void
    {
        $monitoring = app(MonitoringService::class);
        $positions = $monitoring->positionsQuery(['instansi_id' => $usulan->instansi_id])->where('p.kebutuhan', '>', 0)->get();

        foreach ($positions as $p) {
            $usul = max(0, (int) $p->kebutuhan - (int) $p->existing + (int) $p->pensiun);
            if ($usul > 0) {
                $usulan->details()->create([
                    'unit_kerja_id' => $p->unit_kerja_id, 'jabatan_id' => $p->jabatan_id,
                    'kebutuhan_abk' => $p->kebutuhan, 'existing' => $p->existing, 'proyeksi_pensiun' => $p->pensiun,
                    'jumlah_usul' => $usul,
                ]);
            }
        }
    }

    /** Referensi jabatan (struktural, fungsional, pelaksana). */
    private function jabatans(): array
    {
        $rows = [
            ['JPT-PRT-SEKDA', 'Sekretaris Daerah / Sekretaris Jenderal', 'jpt_madya', null, null, 16, 60],
            ['JPT-PRT-KADIS', 'Kepala Dinas / Kepala Badan', 'jpt_pratama', null, null, 14, 60],
            ['ADM-SEKRE', 'Sekretaris Dinas / Kepala Bagian', 'administrator', null, null, 12, 58],
            ['ADM-KABID', 'Kepala Bidang', 'administrator', null, null, 12, 58],
            ['PGW-KASUBAG', 'Kepala Subbagian Umum dan Kepegawaian', 'pengawas', null, null, 9, 58],
            ['JF-ASDM-1', 'Analis Sumber Daya Manusia Aparatur Ahli Pertama', 'fungsional', 'keahlian', 'Ahli Pertama', 8, 58],
            ['JF-ASDM-2', 'Analis Sumber Daya Manusia Aparatur Ahli Muda', 'fungsional', 'keahlian', 'Ahli Muda', 9, 58],
            ['JF-ASDM-3', 'Analis Sumber Daya Manusia Aparatur Ahli Madya', 'fungsional', 'keahlian', 'Ahli Madya', 11, 60],
            ['JF-AK-1', 'Analis Kebijakan Ahli Pertama', 'fungsional', 'keahlian', 'Ahli Pertama', 8, 58],
            ['JF-AK-2', 'Analis Kebijakan Ahli Muda', 'fungsional', 'keahlian', 'Ahli Muda', 9, 58],
            ['JF-PRAKOM-1', 'Pranata Komputer Ahli Pertama', 'fungsional', 'keahlian', 'Ahli Pertama', 8, 58],
            ['JF-PRAKOM-T', 'Pranata Komputer Terampil', 'fungsional', 'keterampilan', 'Terampil', 6, 58],
            ['JF-ARS-T', 'Arsiparis Terampil', 'fungsional', 'keterampilan', 'Terampil', 6, 58],
            ['JF-PERENC-1', 'Perencana Ahli Pertama', 'fungsional', 'keahlian', 'Ahli Pertama', 8, 58],
            ['JF-PERENC-2', 'Perencana Ahli Muda', 'fungsional', 'keahlian', 'Ahli Muda', 9, 58],
            ['JF-APBJ-1', 'Pengelola Pengadaan Barang/Jasa Ahli Pertama', 'fungsional', 'keahlian', 'Ahli Pertama', 8, 58],
            ['JF-AUDITOR-1', 'Auditor Ahli Pertama', 'fungsional', 'keahlian', 'Ahli Pertama', 8, 58],
            ['PLK-PENATA', 'Penata Layanan Operasional', 'pelaksana', null, null, 7, 58],
            ['PLK-PENGELOLA', 'Pengelola Layanan Operasional', 'pelaksana', null, null, 6, 58],
            ['PLK-ADMIN', 'Pengadministrasi Perkantoran', 'pelaksana', null, null, 5, 58],
            ['PLK-OPERATOR', 'Operator Layanan Operasional', 'pelaksana', null, null, 5, 58],
        ];

        $kualifikasi = ['keahlian' => 'S-1/D-IV', 'keterampilan' => 'D-III'];

        return collect($rows)->map(fn ($r) => Jabatan::create([
            'kode' => $r[0], 'nama' => $r[1], 'jenis' => $r[2], 'kategori' => $r[3], 'jenjang' => $r[4],
            'kelas_jabatan' => $r[5], 'bup' => $r[6],
            'kualifikasi_pendidikan' => $kualifikasi[$r[3]] ?? ($r[2] === 'pelaksana' ? 'SMA/D-III' : 'S-1/D-IV'),
            // estimasi kasar belanja pegawai per tahun (gaji + tunjangan) untuk simulasi anggaran
            'estimasi_biaya_tahunan' => 30_000_000 + $r[5] * 9_000_000,
        ]))->keyBy('kode')->all();
    }

    private function units(Instansi $instansi, string $jenis): array
    {
        $pusat = $jenis === 'pusat';
        $induk = UnitKerja::create(['instansi_id' => $instansi->id, 'kode' => 'UK-001', 'nama' => $pusat ? 'Sekretariat Jenderal' : 'Sekretariat Daerah', 'eselon' => 'II', 'urutan' => 1]);

        $opd = $pusat
            ? ['Biro Sumber Daya Manusia' => 'Biro SDM', 'Biro Perencanaan dan Keuangan' => 'Biro Renkeu', 'Direktorat Kebijakan' => 'Dit. Kebijakan', 'Direktorat Pelayanan' => 'Dit. Pelayanan']
            : ['Badan Kepegawaian dan Pengembangan SDM' => 'BKPSDM', 'Dinas Pendidikan' => 'Disdik', 'Dinas Kesehatan' => 'Dinkes', 'Badan Perencanaan Pembangunan Daerah' => 'Bappeda'];

        $units = [$induk];
        $i = -1;
        foreach ($opd as $nama => $singkatan) {
            $i++;
            $unit = UnitKerja::create(['instansi_id' => $instansi->id, 'parent_id' => $induk->id, 'kode' => sprintf('UK-%03d', ($i + 1) * 10), 'nama' => $nama, 'eselon' => $pusat ? 'II' : 'II', 'urutan' => $i + 2]);
            $units[] = $unit;
            foreach (['Sekretariat', 'Bidang Program', 'Bidang Layanan'] as $j => $sub) {
                $units[] = UnitKerja::create(['instansi_id' => $instansi->id, 'parent_id' => $unit->id, 'kode' => sprintf('UK-%03d', ($i + 1) * 10 + $j + 1), 'nama' => $sub.' '.$singkatan, 'eselon' => 'III', 'urutan' => $j + 1]);
            }
        }

        return $units;
    }

    private function pegawaiDanAbk(Instansi $instansi, array $units, array $jabatans, $faker, User $operator, int $bias = 0): void
    {
        $pool = ['JF-ASDM-1', 'JF-ASDM-2', 'JF-AK-1', 'JF-PRAKOM-1', 'JF-PRAKOM-T', 'JF-ARS-T', 'JF-PERENC-1', 'JF-APBJ-1',
            'PLK-PENATA', 'PLK-PENGELOLA', 'PLK-ADMIN', 'PLK-OPERATOR'];

        foreach ($units as $unit) {
            $kodes = $unit->eselon === 'III'
                ? array_merge(['ADM-KABID'], array_rand(array_flip($pool), 4))
                : array_merge([$unit->parent_id ? 'JPT-PRT-KADIS' : 'JPT-PRT-SEKDA', 'PGW-KASUBAG'], array_rand(array_flip($pool), 5));

            foreach ($kodes as $kode) {
                $jabatan = $jabatans[$kode];
                $struktural = in_array($jabatan->jenis, ['jpt_madya', 'jpt_pratama', 'administrator', 'pengawas'], true);
                $kebutuhan = $struktural ? 1 : mt_rand(1, 6);

                // kondisi existing sengaja bervariasi: kosong, kurang, pas, gemuk
                $existing = $struktural ? mt_rand(0, 1) : max(0, $kebutuhan + mt_rand(-3, 2) + $bias);
                if ($jabatan->jenis === 'pelaksana' && mt_rand(0, 4) === 0) {
                    $existing += mt_rand(2, 5);
                }

                for ($n = 0; $n < $existing; $n++) {
                    $lahir = $faker->dateTimeBetween('-59 years', '-24 years');
                    Pegawai::create([
                        'instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $jabatan->id,
                        'nip' => $lahir->format('Ymd').$faker->numerify('20##').str_pad((string) mt_rand(1, 12), 2, '0', STR_PAD_LEFT).mt_rand(1, 2).$faker->numerify('###'),
                        'nama' => $faker->name(),
                        'status_kepegawaian' => mt_rand(0, 4) ? 'pns' : 'pppk',
                        'golongan' => $faker->randomElement(['II/c', 'III/a', 'III/b', 'III/c', 'III/d', 'IV/a']),
                        'pendidikan' => $faker->randomElement(['SMA', 'D-III', 'S-1', 'S-2']),
                        'tanggal_lahir' => $lahir->format('Y-m-d'),
                        'tmt_jabatan' => $faker->dateTimeBetween('-10 years', '-1 month')->format('Y-m-d'),
                    ]);
                }

                // ABK: beban kerja disusun agar menghasilkan kebutuhan yang direncanakan
                $wke = 75000;
                $anjab = AnjabAbk::create([
                    'instansi_id' => $instansi->id, 'unit_kerja_id' => $unit->id, 'jabatan_id' => $jabatan->id,
                    'tahun' => (int) now()->format('Y'), 'waktu_kerja_efektif' => $wke,
                    'ikhtisar_jabatan' => 'Melakukan kegiatan '.strtolower($jabatan->nama).' sesuai ketentuan peraturan perundang-undangan.',
                    'kelas_jabatan' => $jabatan->kelas_jabatan,
                    'pertumbuhan_beban' => [0, 0, 2, 3, 5][mt_rand(0, 4)],
                    'informasi' => [
                        'kualifikasi_pendidikan' => [$jabatan->kualifikasi_pendidikan],
                        'bahan_kerja' => ['Peraturan perundang-undangan terkait', 'Data dan disposisi pimpinan'],
                        'perangkat_kerja' => ['Komputer dan jaringan internet', 'Aplikasi kepegawaian'],
                        'tanggung_jawab' => ['Kebenaran dan ketepatan hasil kerja'],
                        'wewenang' => ['Meminta data kepada unit terkait'],
                        'kondisi_lingkungan' => ['Di dalam ruangan, suhu dan penerangan normal'],
                        'risiko_bahaya' => ['Kelelahan mata dan gangguan postur'],
                    ],
                    'status' => mt_rand(0, 9) ? 'final' : 'draft', 'created_by' => $operator->id,
                ]);
                if ($anjab->status === 'final') {
                    $anjab->update(['finalized_by' => $operator->id, 'finalized_at' => now()]);
                }

                $target = $kebutuhan * $wke;
                $tugas = [
                    ['Menyusun rencana kerja dan bahan kebijakan', 'Dokumen', 0.3],
                    ['Melaksanakan kegiatan teknis sesuai bidang tugas', 'Laporan', 0.5],
                    ['Melakukan evaluasi dan pelaporan', 'Laporan', 0.2],
                ];
                // volume dinyatakan per tahun / per bulan / per hari lalu disetahunkan saat dihitung
                $periode = ['tahun', 'bulan', 'hari'];
                foreach ($tugas as $i => [$uraian, $hasil, $porsi]) {
                    $norma = [120, 60, 240][$i];
                    $faktor = Referensi::PERIODE_PER_TAHUN[$periode[$i]];
                    $anjab->uraianTugas()->create([
                        'uraian_tugas' => $uraian, 'hasil_kerja' => $hasil, 'norma_waktu' => $norma, 'satuan_periode' => $periode[$i],
                        'volume' => max(1, (int) round($target * $porsi / $norma / $faktor)), 'urutan' => $i + 1,
                    ]);
                }
                $anjab->recalculate();
            }
        }
    }
}
