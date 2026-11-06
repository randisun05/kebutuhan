<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pemetaan jabatan SIASN (per ID atau per nama) ke jabatan SIMONKEB. Baris dengan jabatan_id kosong
        // adalah jabatan yang ditemui saat sinkron tetapi belum dipetakan.
        Schema::create('siasn_jabatan_peta', function (Blueprint $table) {
            $table->id();
            $table->string('kunci', 191)->unique(); // "id:<id SIASN>" atau "nama:<nama huruf kecil>"
            $table->string('siasn_jabatan_id', 64)->nullable();
            $table->string('nama_siasn')->nullable();
            $table->foreignId('jabatan_id')->nullable()->constrained('jabatans')->nullOnDelete();
            $table->foreignId('instansi_id')->nullable()->constrained('instansis')->nullOnDelete();
            $table->unsignedInteger('jumlah_pegawai')->default(0); // pada sinkron terakhir
            $table->string('contoh_nip', 18)->nullable();
            $table->timestamp('terakhir_terlihat')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siasn_jabatan_peta');
    }
};
