<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu tabel riwayat untuk semua fitur AI. Hasil disimpan dan dipakai ulang
     * (tidak generate ulang) sampai pengguna meminta "Buat ulang".
     */
    public function up(): void
    {
        Schema::create('ai_riwayat', function (Blueprint $table) {
            $table->id();
            // fitur: anjab_draft, analisis_monitoring, saran_peringatan, ...
            $table->string('jenis', 40);
            // subjek yang dianalisis, contoh: instansi:5|jenis=fungsional, peringatan:12, jabatan:3|unit:7
            $table->string('kunci', 191);
            $table->foreignId('instansi_id')->nullable()->constrained('instansis')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // md5 prompt: bila data sumber berubah, hasil lama ditandai usang
            $table->string('sidik', 32);
            $table->string('model', 60)->nullable();
            $table->text('prompt');
            // teks/markdown atau JSON (draf anjab)
            $table->longText('hasil');
            $table->timestamps();

            $table->index(['jenis', 'kunci', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_riwayat');
    }
};
