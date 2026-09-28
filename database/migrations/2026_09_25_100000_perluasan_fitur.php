<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perluasan: integrasi SIASN, manajemen organisasi, riwayat pegawai,
 * ABK multi-tahun dengan informasi jabatan lengkap, prioritas/anggaran usulan,
 * dan pelacakan pengisian formasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instansis', function (Blueprint $table) {
            $table->string('siasn_instansi_id', 64)->nullable()->after('kode');
            $table->string('siasn_satuan_kerja_id', 64)->nullable()->after('siasn_instansi_id');
        });

        Schema::table('unit_kerjas', function (Blueprint $table) {
            $table->string('siasn_unor_id', 64)->nullable()->after('kode');
            $table->string('nama_jabatan_pimpinan')->nullable()->after('eselon');
            $table->boolean('is_active')->default(true)->after('urutan');
            $table->index('siasn_unor_id');
        });

        Schema::table('jabatans', function (Blueprint $table) {
            // id jabatan fungsional / jabatan pelaksana (fungsional umum) / struktural pada SIASN
            $table->string('siasn_jabatan_id', 64)->nullable()->after('kode');
            // estimasi belanja pegawai per orang per tahun, untuk pertimbangan anggaran
            $table->unsignedBigInteger('estimasi_biaya_tahunan')->nullable()->after('kualifikasi_pendidikan');
            $table->index('siasn_jabatan_id');
        });

        Schema::table('pegawais', function (Blueprint $table) {
            $table->string('siasn_id', 64)->nullable()->after('nip');
            // manual / import / siasn
            $table->string('sumber', 10)->default('manual')->after('is_active');
            $table->timestamp('siasn_synced_at')->nullable()->after('sumber');
        });

        Schema::create('pegawai_riwayats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawais')->cascadeOnDelete();
            // mutasi_unit, ganti_jabatan, status, masuk, keluar
            $table->string('jenis', 20);
            $table->foreignId('dari_unit_id')->nullable()->constrained('unit_kerjas')->nullOnDelete();
            $table->foreignId('ke_unit_id')->nullable()->constrained('unit_kerjas')->nullOnDelete();
            $table->foreignId('dari_jabatan_id')->nullable()->constrained('jabatans')->nullOnDelete();
            $table->foreignId('ke_jabatan_id')->nullable()->constrained('jabatans')->nullOnDelete();
            $table->string('keterangan')->nullable();
            $table->string('sumber', 10)->default('manual');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pegawai_id', 'created_at']);
        });

        // ABK kini disusun per tahun: satu posisi dapat memiliki ABK beberapa tahun
        Schema::table('anjab_abks', function (Blueprint $table) {
            $table->dropUnique(['unit_kerja_id', 'jabatan_id']);
        });
        Schema::table('anjab_abks', function (Blueprint $table) {
            $table->unique(['unit_kerja_id', 'jabatan_id', 'tahun']);
            // informasi jabatan lengkap (kualifikasi, bahan/perangkat kerja, tanggung jawab, wewenang,
            // korelasi, lingkungan kerja, risiko bahaya, syarat jabatan, prestasi yang diharapkan)
            $table->json('informasi')->nullable()->after('ikhtisar_jabatan');
            $table->unsignedTinyInteger('kelas_jabatan')->nullable()->after('informasi');
            // perkiraan pertumbuhan beban kerja per tahun (%) untuk proyeksi 5 tahun
            $table->decimal('pertumbuhan_beban', 5, 2)->default(0)->after('waktu_kerja_efektif');
            $table->foreignId('finalized_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('finalized_by');
        });

        Schema::table('anjab_uraian_tugas', function (Blueprint $table) {
            // periode volume beban kerja: tahun/bulan/minggu/hari (dikonversi ke tahunan)
            $table->string('satuan_periode', 10)->default('tahun')->after('volume');
        });

        Schema::table('usulan_details', function (Blueprint $table) {
            // 1 = tinggi, 2 = sedang, 3 = rendah
            $table->unsignedTinyInteger('prioritas')->default(2)->after('jumlah_usul');
            $table->unsignedInteger('jumlah_terisi')->default(0)->after('jumlah_ditetapkan');
            $table->timestamp('terisi_updated_at')->nullable()->after('jumlah_terisi');
        });

        Schema::create('siasn_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instansi_id')->nullable()->constrained('instansis')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // pegawai / unor
            $table->string('jenis', 10);
            // berjalan / selesai / gagal
            $table->string('status', 10)->default('berjalan');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('berhasil')->default(0);
            $table->unsignedInteger('gagal')->default(0);
            $table->json('pesan')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siasn_sync_logs');
        Schema::table('usulan_details', fn (Blueprint $t) => $t->dropColumn(['prioritas', 'jumlah_terisi', 'terisi_updated_at']));
        Schema::table('anjab_uraian_tugas', fn (Blueprint $t) => $t->dropColumn('satuan_periode'));
        Schema::table('anjab_abks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropUnique(['unit_kerja_id', 'jabatan_id', 'tahun']);
            $table->dropColumn(['informasi', 'kelas_jabatan', 'pertumbuhan_beban', 'finalized_at']);
            $table->unique(['unit_kerja_id', 'jabatan_id']);
        });
        Schema::dropIfExists('pegawai_riwayats');
        Schema::table('pegawais', fn (Blueprint $t) => $t->dropColumn(['siasn_id', 'sumber', 'siasn_synced_at']));
        Schema::table('jabatans', function (Blueprint $t) {
            $t->dropIndex(['siasn_jabatan_id']);
            $t->dropColumn(['siasn_jabatan_id', 'estimasi_biaya_tahunan']);
        });
        Schema::table('unit_kerjas', function (Blueprint $t) {
            $t->dropIndex(['siasn_unor_id']);
            $t->dropColumn(['siasn_unor_id', 'nama_jabatan_pimpinan', 'is_active']);
        });
        Schema::table('instansis', fn (Blueprint $t) => $t->dropColumn(['siasn_instansi_id', 'siasn_satuan_kerja_id']));
    }
};
