<?php

namespace App\Models;

use App\Support\Referensi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnjabUraianTugas extends Model
{
    protected $table = 'anjab_uraian_tugas';

    protected $fillable = ['anjab_abk_id', 'uraian_tugas', 'hasil_kerja', 'volume', 'satuan_periode', 'norma_waktu', 'urutan'];

    protected $attributes = ['satuan_periode' => 'tahun'];

    /** Volume disetahunkan sesuai satuan periode (dasar: 1.250 jam kerja efektif per tahun). */
    public function volumeTahunan(): float
    {
        return $this->volume * (Referensi::PERIODE_PER_TAHUN[$this->satuan_periode] ?? 1);
    }

    public function bebanKerja(): float
    {
        return $this->volumeTahunan() * $this->norma_waktu;
    }

    public function anjabAbk(): BelongsTo
    {
        return $this->belongsTo(AnjabAbk::class);
    }
}
