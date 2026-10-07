<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Riwayat hasil fitur AI (satu tabel untuk semua jenis). */
class AiRiwayat extends Model
{
    protected $table = 'ai_riwayat';

    protected $fillable = ['jenis', 'kunci', 'instansi_id', 'user_id', 'sidik', 'model', 'prompt', 'hasil'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }
}
