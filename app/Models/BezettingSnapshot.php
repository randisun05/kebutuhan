<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BezettingSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['periode', 'instansi_id', 'unit_kerja_id', 'jabatan_id', 'kebutuhan', 'existing', 'pensiun', 'formasi', 'diambil_at'];

    protected $casts = ['periode' => 'date:Y-m-d', 'diambil_at' => 'datetime'];
}
