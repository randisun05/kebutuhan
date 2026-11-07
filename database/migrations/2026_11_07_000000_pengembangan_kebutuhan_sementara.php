<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pengisian massal tercatat per batch agar dapat dibatalkan
        Schema::create('kebutuhan_sementara_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('instansi_id')->constrained('instansis')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sumber', 10);
            $table->unsignedInteger('jumlah_dibuat')->default(0);
            $table->timestamp('dibatalkan_at')->nullable();
            $table->timestamps();
        });

        Schema::table('kebutuhan_sementara', function (Blueprint $table) {
            $table->string('batch', 36)->nullable()->after('dasar')->index();
            // disetujui = dipakai di perhitungan; menunggu = menunggu persetujuan administrator
            $table->string('status', 10)->default('disetujui')->after('batch');
            $table->foreignId('disetujui_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_at')->nullable()->after('disetujui_by');
        });

        // bila diaktifkan, isian operator instansi menunggu persetujuan administrator
        Schema::table('instansis', function (Blueprint $table) {
            $table->boolean('kebutuhan_perlu_persetujuan')->default(false)->after('jalur_kebutuhan');
        });
    }

    public function down(): void
    {
        Schema::table('instansis', fn (Blueprint $t) => $t->dropColumn('kebutuhan_perlu_persetujuan'));
        Schema::table('kebutuhan_sementara', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disetujui_by');
            $table->dropColumn(['disetujui_at', 'status', 'batch']);
        });
        Schema::dropIfExists('kebutuhan_sementara_batches');
    }
};
