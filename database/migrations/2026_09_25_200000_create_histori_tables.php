<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekam jejak bulanan kebutuhan vs existing per posisi (unit × jabatan) untuk
 * analisis tren dan pergerakan data existing dari waktu ke waktu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bezetting_snapshots', function (Blueprint $table) {
            $table->id();
            // hari pertama bulan periode
            $table->date('periode');
            $table->foreignId('instansi_id')->constrained('instansis')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerjas')->cascadeOnDelete();
            $table->foreignId('jabatan_id')->constrained('jabatans')->cascadeOnDelete();
            $table->unsignedInteger('kebutuhan')->default(0);
            $table->unsignedInteger('existing')->default(0);
            $table->unsignedInteger('pensiun')->default(0);
            $table->unsignedInteger('formasi')->default(0);
            $table->timestamp('diambil_at')->nullable();

            $table->unique(['periode', 'unit_kerja_id', 'jabatan_id']);
            $table->index(['instansi_id', 'periode']);
        });

        Schema::create('peringatans', function (Blueprint $table) {
            $table->id();
            // kode aturan, contoh: jabatan_kosong, unit_gemuk, stagnan, abk_kedaluwarsa
            $table->string('kode', 40);
            // kritis / tinggi / sedang / rendah
            $table->string('tingkat', 10);
            $table->foreignId('instansi_id')->nullable()->constrained('instansis')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerjas')->cascadeOnDelete();
            $table->foreignId('jabatan_id')->nullable()->constrained('jabatans')->cascadeOnDelete();
            // kunci unik objek peringatan agar tidak dobel (kode + objek)
            $table->string('kunci', 120)->unique();
            $table->string('judul');
            $table->text('pesan');
            $table->json('data')->nullable();
            $table->string('url')->nullable();
            // aktif / ditindaklanjuti / selesai (otomatis bila kondisi hilang)
            $table->string('status', 16)->default('aktif');
            $table->text('catatan_tindak_lanjut')->nullable();
            $table->foreignId('ditangani_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pertama_terdeteksi_at')->nullable();
            $table->timestamp('terakhir_terdeteksi_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'tingkat']);
            $table->index(['instansi_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peringatans');
        Schema::dropIfExists('bezetting_snapshots');
    }
};
