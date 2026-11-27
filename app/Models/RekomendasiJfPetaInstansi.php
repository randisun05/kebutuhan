<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pemetaan manual nama instansi di Excel ke referensi instansi BKN. */
class RekomendasiJfPetaInstansi extends Model
{
    protected $table = 'rekomendasi_jf_peta_instansi';

    protected $guarded = [];

    public function refInstansi(): BelongsTo
    {
        return $this->belongsTo(RefInstansiBkn::class, 'ref_instansi_id');
    }
}
