<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proses bisnis penyusunan kebutuhan (hulu ke hilir):
 * Anjab/ABK -> Usulan kebutuhan -> Verifikasi & pertimbangan teknis BKN
 * -> Validasi KemenPANRB -> Penetapan kebutuhan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Hasil Analisis Jabatan & Analisis Beban Kerja per jabatan pada unit kerja
        Schema::create('anjab_abks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->constrained('instansis')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerjas')->cascadeOnDelete();
            $table->foreignId('jabatan_id')->constrained('jabatans')->restrictOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->text('ikhtisar_jabatan')->nullable();
            // Waktu kerja efektif per tahun dalam menit (1.250 jam = 75.000 menit)
            $table->unsignedInteger('waktu_kerja_efektif')->default(75000);
            $table->decimal('total_beban_kerja', 14, 2)->default(0);
            $table->decimal('kebutuhan_hitung', 10, 2)->default(0);
            $table->unsignedInteger('kebutuhan')->default(0);
            // draft / final
            $table->string('status', 10)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['unit_kerja_id', 'jabatan_id']);
            $table->index(['instansi_id', 'status']);
        });

        Schema::create('anjab_uraian_tugas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anjab_abk_id')->constrained('anjab_abks')->cascadeOnDelete();
            $table->text('uraian_tugas');
            $table->string('hasil_kerja')->nullable();
            // jumlah hasil kerja per tahun
            $table->unsignedInteger('volume')->default(0);
            // norma waktu penyelesaian satu hasil kerja (menit)
            $table->unsignedInteger('norma_waktu')->default(0);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('usulans', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 50)->unique();
            $table->foreignId('instansi_id')->constrained('instansis')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->string('periode', 20)->nullable();
            // pns / pppk
            $table->string('jenis_asn', 10)->default('pns');
            $table->string('perihal');
            $table->text('keterangan')->nullable();
            $table->string('surat_pengantar')->nullable();
            // draft, diajukan, verifikasi_bkn, pertimbangan_teknis, validasi_kemenpan, ditetapkan, dikembalikan, ditolak
            $table->string('status', 30)->default('draft');
            $table->text('catatan_terakhir')->nullable();
            $table->timestamp('diajukan_at')->nullable();
            $table->timestamp('ditetapkan_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['instansi_id', 'status']);
            $table->index(['status', 'tahun']);
        });

        Schema::create('usulan_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usulan_id')->constrained('usulans')->cascadeOnDelete();
            $table->foreignId('unit_kerja_id')->constrained('unit_kerjas')->cascadeOnDelete();
            $table->foreignId('jabatan_id')->constrained('jabatans')->restrictOnDelete();
            // snapshot kondisi saat usulan disusun
            $table->unsignedInteger('kebutuhan_abk')->default(0);
            $table->unsignedInteger('existing')->default(0);
            $table->unsignedInteger('proyeksi_pensiun')->default(0);
            $table->unsignedInteger('jumlah_usul')->default(0);
            $table->unsignedInteger('jumlah_rekomendasi')->nullable();
            $table->unsignedInteger('jumlah_ditetapkan')->nullable();
            $table->string('kualifikasi_pendidikan')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['usulan_id', 'unit_kerja_id', 'jabatan_id']);
        });

        Schema::create('usulan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usulan_id')->constrained('usulans')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi', 30);
            $table->string('dari_status', 30)->nullable();
            $table->string('ke_status', 30);
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('penetapans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usulan_id')->unique()->constrained('usulans')->cascadeOnDelete();
            $table->foreignId('instansi_id')->constrained('instansis')->cascadeOnDelete();
            $table->string('nomor_sk', 100);
            $table->date('tanggal_sk');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('total_ditetapkan')->default(0);
            $table->string('file_sk')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('ditetapkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['instansi_id', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penetapans');
        Schema::dropIfExists('usulan_logs');
        Schema::dropIfExists('usulan_details');
        Schema::dropIfExists('usulans');
        Schema::dropIfExists('anjab_uraian_tugas');
        Schema::dropIfExists('anjab_abks');
    }
};
