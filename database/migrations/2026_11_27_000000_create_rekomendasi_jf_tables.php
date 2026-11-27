<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rekomendasi/penetapan kebutuhan JF MASN dengan skema ternormalisasi dan dinamis:
 * jabatan pokok, jenjang, dan sumber angka adalah DATA (dikelola lewat menu manajemen), bukan konstanta kode;
 * instansi dikenali lewat alias nama sehingga ganti nama tidak memutus data; angka disimpan sebagai fakta
 * berversi (riwayat tidak hilang); tata letak Excel disimpan sebagai profil impor yang dapat diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Instansi pembina JF (mis. BKN untuk JF SDM Aparatur). Setiap pembina memiliki JF, sumber angka, dan profil impor
        // sendiri, dan penggunanya hanya dapat mengakses data haknya. Pembina baru = baris baru dengan metode yang sama.
        Schema::create('instansi_pembina', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 32)->unique();
            $table->string('nama')->unique();
            $table->boolean('is_active')->default(true);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('jf_jabatan_pokok', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembina_id')->constrained('instansi_pembina')->restrictOnDelete();
            $table->string('kode', 32)->unique();
            $table->string('nama')->unique();
            $table->string('rumpun', 16);            // keahlian | keterampilan
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('jf_jenjang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 24)->unique();
            $table->string('nama', 48);
            $table->string('rumpun', 16);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Jenjang yang berlaku untuk tiap jabatan pokok, beserta posisinya pada blok kolom Excel (1, 2, 3, ...).
        Schema::create('jf_jabatan_jenjang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jabatan_pokok_id')->constrained('jf_jabatan_pokok')->cascadeOnDelete();
            $table->foreignId('jenjang_id')->constrained('jf_jenjang')->restrictOnDelete();
            $table->unsignedTinyInteger('urutan_kolom');
            $table->timestamps();

            $table->unique(['jabatan_pokok_id', 'jenjang_id']);
            $table->unique(['jabatan_pokok_id', 'urutan_kolom']);
        });

        // Daftar sumber angka. Sumber baru = baris baru, bukan migrasi.
        Schema::create('sumber_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembina_id')->constrained('instansi_pembina')->restrictOnDelete();
            $table->string('kode', 32);
            $table->string('nama');
            $table->string('jenis', 12);              // kebutuhan | bezetting
            $table->string('tingkat', 8);             // instansi | unit
            $table->string('sifat', 8);               // foto (saat dokumen) | hidup (kondisi kini)
            $table->string('adapter', 16)->default('fakta'); // fakta (tabel kebutuhan_fakta) | abk | pegawai | dashboard
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['pembina_id', 'kode']);
        });

        // Nama instansi sebagaimana tertulis di sumber, dipetakan ke referensi instansi BKN.
        Schema::create('instansi_alias', function (Blueprint $table) {
            $table->id();
            $table->string('nama_normal', 191)->unique();
            $table->string('nama');
            $table->foreignId('ref_instansi_id')->nullable()->constrained('ref_instansi_bkn')->nullOnDelete();
            $table->string('jenis_instansi', 16)->nullable();
            $table->string('asal', 8)->default('impor');   // impor | manual
            $table->timestamps();
        });

        // Tata letak berkas Excel sumber (sheet, kolom, blok angka) sebagai data yang dapat diubah.
        Schema::create('impor_profil', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembina_id')->constrained('instansi_pembina')->restrictOnDelete();
            $table->string('kode', 32)->unique();
            $table->string('nama');
            $table->string('sheet');
            $table->json('pemetaan');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rekomendasi_jf_impor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('impor_profil_id')->nullable()->constrained('impor_profil')->nullOnDelete();
            $table->string('nama_berkas');
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->unsignedInteger('dibuat')->default(0);
            $table->unsignedInteger('diperbarui')->default(0);
            $table->unsignedInteger('fakta_baru')->default(0);
            $table->json('peringatan')->nullable();
            $table->timestamps();
        });

        // Satu baris = instansi (alias) × jabatan pokok: status dan catatan.
        Schema::create('rekomendasi_jf', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alias_id')->constrained('instansi_alias')->cascadeOnDelete();
            $table->foreignId('jabatan_pokok_id')->constrained('jf_jabatan_pokok')->restrictOnDelete();
            $table->string('pokja', 16)->nullable();         // opsional; pengelompokan internal pembina
            $table->string('wilayah_kerja')->nullable();     // opsional
            $table->string('status_rekom', 40)->nullable();
            $table->string('jenis_rekom', 24)->nullable();
            $table->string('status_menpan', 24)->nullable();
            $table->boolean('ditetapkan_sesuai_bezetting')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('impor_id')->nullable()->constrained('rekomendasi_jf_impor')->nullOnDelete();
            $table->timestamps();

            $table->unique(['alias_id', 'jabatan_pokok_id']);
        });

        // Fakta angka berversi: tidak ditimpa. is_terbaru menandai nilai yang berlaku. unit_kerja_id kosong = tingkat instansi.
        Schema::create('kebutuhan_fakta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_jf_id')->constrained('rekomendasi_jf')->cascadeOnDelete();
            $table->foreignId('jenjang_id')->constrained('jf_jenjang')->restrictOnDelete();
            $table->foreignId('sumber_id')->constrained('sumber_data')->restrictOnDelete();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerjas')->nullOnDelete();
            $table->integer('nilai')->nullable();           // null = nilai dicabut pada impor berikutnya
            $table->date('tanggal_data')->nullable();
            $table->foreignId('impor_id')->nullable()->constrained('rekomendasi_jf_impor')->nullOnDelete();
            $table->boolean('is_terbaru')->default(true);
            $table->timestamps();

            $table->index(['rekomendasi_jf_id', 'sumber_id', 'jenjang_id', 'is_terbaru'], 'fakta_terbaru_idx');
        });

        Schema::create('rekomendasi_jf_surat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_jf_id')->constrained('rekomendasi_jf')->cascadeOnDelete();
            $table->string('jenis', 20); // rekomendasi | pengembalian | penetapan_menpan
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->string('nomor', 191);
            $table->date('tanggal')->nullable();
            $table->string('penandatangan_jabatan')->nullable();
            $table->string('penandatangan_nama')->nullable();
            $table->text('tautan')->nullable();
            $table->timestamps();
        });

        $this->isiBawaan();
    }

    /** Master bawaan agar modul langsung dapat dipakai; seluruhnya dapat diubah lewat menu manajemen. */
    private function isiBawaan(): void
    {
        $now = now();
        $pembinaId = DB::table('instansi_pembina')->insertGetId([
            'kode' => 'bkn', 'nama' => 'Badan Kepegawaian Negara', 'is_active' => true, 'keterangan' => 'Pembina JF SDM Aparatur', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $jab = [['analis', 'Analis SDM Aparatur', 'keahlian', 1], ['pranata', 'Pranata SDM Aparatur', 'keterampilan', 2],
            ['asesor', 'Asesor SDM Aparatur', 'keahlian', 3], ['auditor', 'Auditor Manajemen ASN', 'keahlian', 4]];
        foreach ($jab as [$kode, $nama, $rumpun, $urutan]) {
            DB::table('jf_jabatan_pokok')->insert(compact('kode', 'nama', 'rumpun', 'urutan') + ['pembina_id' => $pembinaId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $jenjang = [['pertama', 'Pertama', 'keahlian', 1], ['muda', 'Muda', 'keahlian', 2], ['madya', 'Madya', 'keahlian', 3], ['utama', 'Utama', 'keahlian', 4],
            ['terampil', 'Terampil', 'keterampilan', 1], ['mahir', 'Mahir', 'keterampilan', 2], ['penyelia', 'Penyelia', 'keterampilan', 3]];
        foreach ($jenjang as [$kode, $nama, $rumpun, $urutan]) {
            DB::table('jf_jenjang')->insert(compact('kode', 'nama', 'rumpun', 'urutan') + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        // jenjang yang berlaku per jabatan (Pranata tanpa jenjang keempat)
        $idJab = DB::table('jf_jabatan_pokok')->pluck('id', 'kode');
        $idJen = DB::table('jf_jenjang')->pluck('id', 'kode');
        $pasang = ['analis' => ['pertama', 'muda', 'madya', 'utama'], 'asesor' => ['pertama', 'muda', 'madya', 'utama'], 'auditor' => ['pertama', 'muda', 'madya', 'utama'],
            'pranata' => ['terampil', 'mahir', 'penyelia']];
        foreach ($pasang as $kodeJab => $daftar) {
            foreach ($daftar as $i => $kodeJen) {
                DB::table('jf_jabatan_jenjang')->insert(['jabatan_pokok_id' => $idJab[$kodeJab], 'jenjang_id' => $idJen[$kodeJen], 'urutan_kolom' => $i + 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $sumber = [
            ['bezetting_rekom', 'Bezetting saat rekomendasi', 'bezetting', 'instansi', 'foto', 'fakta', 1, 'Jumlah pejabat pada saat rekomendasi disusun (foto, bukan kondisi kini).'],
            ['rekom_bkn', 'Rekomendasi BKN', 'kebutuhan', 'instansi', 'foto', 'fakta', 2, 'Kolom "Perhitungan formasi" pada Excel rekap.'],
            ['penetapan_menpan', 'Penetapan KemenPANRB', 'kebutuhan', 'instansi', 'foto', 'fakta', 3, null],
        ];
        foreach ($sumber as [$kode, $nama, $jenis, $tingkat, $sifat, $adapter, $urutan, $ket]) {
            DB::table('sumber_data')->insert(compact('kode', 'nama', 'jenis', 'tingkat', 'sifat', 'adapter', 'urutan') + ['pembina_id' => $pembinaId, 'is_active' => true, 'keterangan' => $ket, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Profil impor bawaan = tata letak sheet "Input Data (Instansi Baru)" pada Excel rekap tim data.
        DB::table('impor_profil')->insert([
            'pembina_id' => $pembinaId, 'kode' => 'rekap-jfmasn', 'nama' => 'Rekap Kebutuhan JFMASN (tim data)', 'sheet' => 'Input Data (Instansi Baru)', 'is_active' => true,
            'pemetaan' => json_encode([
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
            ]),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        foreach (['rekomendasi_jf_surat', 'kebutuhan_fakta', 'rekomendasi_jf', 'rekomendasi_jf_impor', 'impor_profil', 'instansi_alias', 'sumber_data', 'jf_jabatan_jenjang', 'jf_jenjang', 'jf_jabatan_pokok', 'instansi_pembina'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
