<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsulanDetail extends Model
{
    protected $fillable = [
        'usulan_id', 'unit_kerja_id', 'jabatan_id', 'kebutuhan_abk', 'existing', 'proyeksi_pensiun',
        'jumlah_usul', 'jumlah_rekomendasi', 'jumlah_ditetapkan', 'kualifikasi_pendidikan', 'keterangan',
    ];

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }
}
