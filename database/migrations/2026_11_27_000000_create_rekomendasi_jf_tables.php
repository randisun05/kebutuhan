<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan setiap impor Excel rekapitulasi kebutuhan JF MASN dari tim data.
        Schema::create('rekomendasi_jf_impor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_berkas');
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->unsignedInteger('dibuat')->default(0);
            $table->unsignedInteger('diperbarui')->default(0);
            $table->json('peringatan')->nullable();
            $table->timestamps();
        });

        // Pemetaan manual nama instansi di Excel ke referensi instansi BKN (dipertahankan antar impor).
        Schema::create('rekomendasi_jf_peta_instansi', function (Blueprint $table) {
            $table->id();
            $table->string('nama_normal', 191)->unique();
            $table->string('nama_excel');
            $table->foreignId('ref_instansi_id')->nullable()->constrained('ref_instansi_bkn')->nullOnDelete();
            $table->timestamps();
        });

        // Satu baris = instansi × jabatan pokok (Analis, Pranata, Asesor, Auditor).
        Schema::create('rekomendasi_jf', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ref_instansi_id')->nullable()->constrained('ref_instansi_bkn')->nullOnDelete();
            $table->string('nama_instansi');
            $table->string('nama_instansi_normal', 191)->index();
            $table->string('jabatan_pokok', 64);
            $table->string('rumpun', 12); // keahlian | keterampilan
            $table->string('jenis_instansi', 16)->nullable();
            $table->string('pokja', 16)->nullable();          // opsional, untuk kepentingan sementara
            $table->string('wilayah_kerja')->nullable();       // opsional
            $table->string('status_rekom', 40)->nullable();    // DONE, Belum Usul, Dikembalikan, ...
            $table->string('jenis_rekom', 24)->nullable();     // Rekom baru / Rekom tambahan
            $table->string('status_menpan', 24)->nullable();   // ditetapkan bila ada penetapan Menpan
            $table->boolean('ditetapkan_sesuai_bezetting')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('impor_id')->nullable()->constrained('rekomendasi_jf_impor')->nullOnDelete();
            $table->timestamps();

            $table->unique(['nama_instansi_normal', 'jabatan_pokok']);
        });

        // Angka per jenjang. Lowongan tidak disimpan: dihitung ulang = bezetting − rekomendasi BKN.
        Schema::create('rekomendasi_jf_jenjang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_jf_id')->constrained('rekomendasi_jf')->cascadeOnDelete();
            $table->string('jenjang', 12); // pertama|muda|madya|utama (keahlian) | terampil|mahir|penyelia (keterampilan)
            $table->integer('bezetting_rekom')->nullable();   // bezetting saat rekomendasi (foto, bukan kondisi kini)
            $table->integer('rekom_bkn')->nullable();         // kolom "Perhitungan formasi" = jumlah rekomendasi BKN
            $table->integer('penetapan_menpan')->nullable();
            $table->timestamps();

            $table->unique(['rekomendasi_jf_id', 'jenjang']);
        });

        // Satu surat = satu baris (rekomendasi, rekomendasi tambahan, pengembalian, penetapan Menpan).
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
    }

    public function down(): void
    {
        Schema::dropIfExists('rekomendasi_jf_surat');
        Schema::dropIfExists('rekomendasi_jf_jenjang');
        Schema::dropIfExists('rekomendasi_jf');
        Schema::dropIfExists('rekomendasi_jf_peta_instansi');
        Schema::dropIfExists('rekomendasi_jf_impor');
    }
};
