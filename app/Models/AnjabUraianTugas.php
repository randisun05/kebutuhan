<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnjabUraianTugas extends Model
{
    protected $table = 'anjab_uraian_tugas';

    protected $fillable = ['anjab_abk_id', 'uraian_tugas', 'hasil_kerja', 'volume', 'norma_waktu', 'urutan'];

    public function anjabAbk(): BelongsTo
    {
        return $this->belongsTo(AnjabAbk::class);
    }
}
