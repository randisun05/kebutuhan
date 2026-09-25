<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data master: instansi, unit kerja (organisasi), referensi jabatan
 * dan data pegawai existing (bezetting).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instansis', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            // pusat = kementerian/lembaga, provinsi, kabupaten, kota
            $table->string('jenis', 20)->default('pusat');
            $table->string('provinsi')->nullable();
            $table->string('alamat')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('nip', 18)->nullable()->unique()->after('name');
            // admin, verifikator_bkn, validator_kemenpan, operator_instansi, pimpinan
            $table->string('role', 30)->default('operator_instansi')->after('password');
            $table->foreignId('instansi_id')->nullable()->after('role')->constrained('instansis')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('instansi_id');
        });

        Schema::create('unit_kerjas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansis')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('unit_kerjas')->nullOnDelete();
            $table->string('kode', 30)->nullable();
            $table->string('nama');
            // I, II, III, IV, non (unit non struktural / kelompok JF)
            $table->string('eselon', 10)->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->index(['instansi_id', 'parent_id']);
        });

        Schema::create('jabatans', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('nama');
            // jpt_utama, jpt_madya, jpt_pratama, administrator, pengawas, pelaksana, fungsional
            $table->string('jenis', 20);
            // keahlian / keterampilan (khusus fungsional)
            $table->string('kategori', 20)->nullable();
            $table->string('jenjang', 50)->nullable();
            $table->unsignedTinyInteger('kelas_jabatan')->nullable();
            // Batas Usia Pensiun (tahun)
            $table->unsignedTinyInteger('bup')->default(58);
            $table->string('kualifikasi_pendidikan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('jenis');
        });

        Schema::create('pegawais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansis')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerjas')->cascadeOnDelete();
            $table->foreignId('jabatan_id')->constrained('jabatans')->restrictOnDelete();
            $table->string('nip', 18)->unique();
            $table->string('nama');
            // pns / pppk
            $table->string('status_kepegawaian', 10)->default('pns');
            $table->string('golongan', 10)->nullable();
            $table->string('pendidikan', 50)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->date('tmt_jabatan')->nullable();
            // Dihitung otomatis dari tanggal lahir + BUP jabatan
            $table->date('tmt_pensiun')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['unit_kerja_id', 'jabatan_id', 'is_active']);
            $table->index(['instansi_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawais');
        Schema::dropIfExists('jabatans');
        Schema::dropIfExists('unit_kerjas');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instansi_id');
            $table->dropUnique(['nip']);
            $table->dropColumn(['nip', 'role', 'is_active']);
        });
        Schema::dropIfExists('instansis');
    }
};
