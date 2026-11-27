<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RekomendasiJfSurat extends Model
{
    protected $table = 'rekomendasi_jf_surat';

    protected $guarded = [];

    protected $casts = ['tanggal' => 'date'];
}
