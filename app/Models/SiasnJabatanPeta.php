<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pemetaan jabatan SIASN ke jabatan SIMONKEB; jabatan_id kosong = belum dipetakan. */
class SiasnJabatanPeta extends Model
{
    protected $table = 'siasn_jabatan_peta';

    protected $fillable = ['kunci', 'siasn_jabatan_id', 'nama_siasn', 'jabatan_id', 'instansi_id', 'jumlah_pegawai', 'contoh_nip', 'terakhir_terlihat'];

    protected $casts = ['terakhir_terlihat' => 'datetime'];

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }
}
