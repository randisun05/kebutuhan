<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kaitan jabatan SIMONKEB ke master JF (jabatan pokok + jenjang) agar ABK dan pegawai dapat disejajarkan dengan
        // rekomendasi/penetapan per instansi × jabatan pokok × jenjang. Opsional: jabatan tanpa kaitan diabaikan di perbandingan.
        Schema::table('jabatans', function (Blueprint $table) {
            $table->foreignId('jf_jabatan_pokok_id')->nullable()->after('jenjang')->constrained('jf_jabatan_pokok')->nullOnDelete();
            $table->foreignId('jf_jenjang_id')->nullable()->after('jf_jabatan_pokok_id')->constrained('jf_jenjang')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jabatans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jf_jenjang_id');
            $table->dropConstrainedForeignId('jf_jabatan_pokok_id');
        });
    }
};
