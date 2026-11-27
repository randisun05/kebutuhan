<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RekomendasiJfJenjang extends Model
{
    protected $table = 'rekomendasi_jf_jenjang';

    protected $guarded = [];

    /** Lowongan = bezetting saat rekomendasi − rekomendasi BKN (negatif = masih kurang). */
    public function getLowonganAttribute(): ?int
    {
        return $this->bezetting_rekom === null || $this->rekom_bkn === null ? null : $this->bezetting_rekom - $this->rekom_bkn;
    }
}
