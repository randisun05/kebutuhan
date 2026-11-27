<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RekomendasiJfImpor extends Model
{
    protected $table = 'rekomendasi_jf_impor';

    protected $guarded = [];

    protected $casts = ['peringatan' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
