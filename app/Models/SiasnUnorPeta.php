<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pemetaan unor SIASN ke unit kerja SIMONKEB; unit_kerja_id kosong = belum dipetakan. */
class SiasnUnorPeta extends Model
{
    protected $table = 'siasn_unor_peta';

    protected $fillable = ['instansi_id', 'siasn_unor_id', 'nama_siasn', 'unit_kerja_id', 'jumlah_pegawai', 'contoh_nip', 'terakhir_terlihat'];

    protected $casts = ['terakhir_terlihat' => 'datetime'];

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }
}
