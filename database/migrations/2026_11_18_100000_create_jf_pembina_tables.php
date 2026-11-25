<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data eksisting (bezetting) JF bidang manajemen ASN dari API instansi pembina BKN
 * (/pns/list-asn-pembinajf), disalin dari proyek ujikom-pusbin. Dipakai sebagai pembanding
 * kebutuhan (ABK) dan bezetting internal.
 *
 * Referensi instansi BKN (654 entri) disimpan di tabel tersendiri dan dipetakan opsional ke
 * `instansis` lewat siasn_instansi_id, supaya master instansi aplikasi tidak penuh oleh instansi
 * yang tidak dikelola.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_instansi_bkn', function (Blueprint $table) {
            $table->id();
            $table->string('siasn_id', 64)->unique();      // instansiKerjaId BKN
            $table->string('nama');
            $table->string('jenis', 2)->nullable();         // P = pusat, D = daerah
            $table->string('kode', 16)->nullable();         // CEPAT_KODE
            $table->string('jenis_instansi', 16)->nullable(); // LPNK, LNS, ...
            $table->foreignId('instansi_id')->nullable()->constrained('instansis')->nullOnDelete();
            $table->unsignedInteger('jf_asn_total')->nullable(); // count terakhir dari API
            $table->timestamp('jf_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('jf_asn', function (Blueprint $table) {
            $table->id();
            $table->string('nip', 32)->unique();
            $table->string('siasn_id', 64)->nullable();
            $table->string('nama');
            $table->foreignId('ref_instansi_id')->constrained('ref_instansi_bkn')->cascadeOnDelete();
            $table->foreignId('instansi_id')->nullable()->constrained('instansis')->nullOnDelete();
            $table->foreignId('jabatan_id')->nullable()->constrained('jabatans')->nullOnDelete();
            // dari data-utama (diisi bertahap, null = belum diambil)
            $table->string('jabatan_fungsional_id', 64)->nullable()->index();
            $table->string('jabatan_fungsional_nama')->nullable();
            $table->string('jabatan_pokok')->nullable()->index();
            $table->string('jenjang', 24)->nullable()->index();
            $table->string('unor_nama')->nullable();
            $table->string('gol_ruang', 16)->nullable();
            $table->date('tmt_jabatan')->nullable();
            $table->string('kedudukan', 64)->nullable();
            $table->timestamp('detail_synced_at')->nullable();
            $table->unsignedTinyInteger('detail_attempts')->default(0);
            // is_active=false bila tidak muncul lagi di list BKN
            $table->boolean('is_active')->default(true)->index();
            // bkn (sinkron API), import (Excel/ujikom), manual (admin; tidak ditimpa sinkron)
            $table->string('source', 10)->default('bkn')->index();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['instansi_id', 'jabatan_id', 'is_active']);
            $table->index(['ref_instansi_id', 'is_active']);
        });

        Schema::create('jf_asn_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status', 16)->default('running')->index(); // running|done|failed
            $table->boolean('with_detail')->default(true);
            $table->unsignedInteger('instansi_total')->default(0);
            $table->unsignedInteger('instansi_done')->default(0);
            $table->unsignedInteger('asn_seen')->default(0);
            $table->unsignedInteger('asn_new')->default(0);
            $table->unsignedInteger('asn_deactivated')->default(0);
            $table->unsignedInteger('detail_fetched')->default(0);
            $table->unsignedInteger('detail_failed')->default(0);
            $table->json('failed_instansi')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // Status per instansi per putaran: checkpoint agar putaran yang macet bisa dilanjutkan.
        Schema::create('jf_asn_sync_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('jf_asn_sync_runs')->cascadeOnDelete();
            $table->foreignId('ref_instansi_id')->constrained('ref_instansi_bkn')->cascadeOnDelete();
            // pending -> listing -> details -> done | failed
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('chunks_total')->default(0);
            $table->unsignedSmallInteger('chunks_done')->default(0);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['run_id', 'ref_instansi_id']);
            $table->index(['run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jf_asn_sync_items');
        Schema::dropIfExists('jf_asn_sync_runs');
        Schema::dropIfExists('jf_asn');
        Schema::dropIfExists('ref_instansi_bkn');
    }
};
