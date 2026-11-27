<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengguna berperan 'pembina' terikat pada satu instansi pembina dan hanya mengakses data pembina itu.
        // Banyak pengguna boleh berbagi satu pembina.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('pembina_id')->nullable()->after('instansi_id')->constrained('instansi_pembina')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('pembina_id'));
    }
};
