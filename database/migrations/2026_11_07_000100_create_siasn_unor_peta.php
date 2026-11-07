<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pemetaan unor SIASN ke unit kerja SIMONKEB per instansi (boleh banyak unor ke satu unit).
        // Baris dengan unit_kerja_id kosong adalah unor yang ditemui saat sinkron tetapi belum dipetakan.
        Schema::create('siasn_unor_peta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansis')->cascadeOnDelete();
            $table->string('siasn_unor_id', 64);
            $table->string('nama_siasn')->nullable();
            $table->foreignId('unit_kerja_id')->nullable()->constrained('unit_kerjas')->nullOnDelete();
            $table->unsignedInteger('jumlah_pegawai')->default(0); // pada sinkron terakhir
            $table->string('contoh_nip', 18)->nullable();
            $table->timestamp('terakhir_terlihat')->nullable();
            $table->timestamps();

            $table->unique(['instansi_id', 'siasn_unor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siasn_unor_peta');
    }
};
