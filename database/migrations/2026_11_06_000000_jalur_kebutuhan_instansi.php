<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jalur kebutuhan per instansi: 'abk' (standar, dari ABK final) atau 'manual' (kebutuhan
        // sementara diisi dari existing/impor). Pada jalur manual peringatan "ABK belum ada" tidak dibuat.
        Schema::table('instansis', function (Blueprint $table) {
            $table->string('jalur_kebutuhan', 10)->default('abk')->after('jenis');
        });
    }

    public function down(): void
    {
        Schema::table('instansis', fn (Blueprint $t) => $t->dropColumn('jalur_kebutuhan'));
    }
};
