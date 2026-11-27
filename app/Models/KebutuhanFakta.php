<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu angka: rekomendasi × jenjang × sumber (× unit, opsional). Berversi: nilai lama dipertahankan dengan is_terbaru = false. */
class KebutuhanFakta extends Model
{
    protected $table = 'kebutuhan_fakta';

    protected $guarded = [];

    protected $casts = ['is_terbaru' => 'boolean', 'tanggal_data' => 'date'];

    public function rekomendasi(): BelongsTo
    {
        return $this->belongsTo(RekomendasiJf::class, 'rekomendasi_jf_id');
    }

    public function jenjang(): BelongsTo
    {
        return $this->belongsTo(JfJenjang::class, 'jenjang_id');
    }

    public function sumber(): BelongsTo
    {
        return $this->belongsTo(SumberData::class, 'sumber_id');
    }
}
