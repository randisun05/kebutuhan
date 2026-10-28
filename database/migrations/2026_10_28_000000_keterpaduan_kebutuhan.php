<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kebutuhan sementara per posisi: dipakai selama posisi belum punya ABK final,
        // sehingga kebutuhan tetap dapat dihitung tanpa menunggu ABK.
        Schema::create('kebutuhan_sementara', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansis')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerjas')->cascadeOnDelete();
            $table->foreignId('jabatan_id')->constrained('jabatans')->cascadeOnDelete();
            $table->unsignedInteger('jumlah');
            // dasar penetapan angka, contoh: peta jabatan 2024, formasi lama, kesepakatan pimpinan
            $table->string('dasar', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_kerja_id', 'jabatan_id']);
        });

        // rincian perhitungan saran usulan saat rincian disusun (snapshot)
        Schema::table('usulan_details', function (Blueprint $table) {
            $table->string('sumber_kebutuhan', 10)->nullable()->after('kebutuhan_abk');
            $table->unsignedInteger('formasi_belum_terisi')->default(0)->after('proyeksi_pensiun');
            $table->unsignedInteger('usulan_proses')->default(0)->after('formasi_belum_terisi');
            $table->unsignedInteger('redistribusi_masuk')->default(0)->after('usulan_proses');
        });

        // pegawai hasil pengisian formasi: menautkan pegawai ke rincian formasi yang ditetapkan
        Schema::table('pegawais', function (Blueprint $table) {
            $table->foreignId('usulan_detail_id')->nullable()->after('jabatan_id')->constrained('usulan_details')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pegawais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usulan_detail_id');
        });
        Schema::table('usulan_details', fn (Blueprint $t) => $t->dropColumn(['sumber_kebutuhan', 'formasi_belum_terisi', 'usulan_proses', 'redistribusi_masuk']));
        Schema::dropIfExists('kebutuhan_sementara');
    }
};
