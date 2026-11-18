<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Referensi instansi BKN (instansiKerjaId), dipetakan opsional ke instansi aplikasi. */
class RefInstansiBkn extends Model
{
    protected $table = 'ref_instansi_bkn';

    protected $guarded = [];

    protected $casts = ['jf_synced_at' => 'datetime'];

    /** Normalisasi nama untuk mencocokkan referensi BKN dengan master instansi. */
    public static function normalisasiNama(?string $nama): string
    {
        $nama = strtolower(str_replace(['Kab.', 'Kabupaten'], 'kabupaten', (string) $nama));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $nama));
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function jfAsns(): HasMany
    {
        return $this->hasMany(JfAsn::class, 'ref_instansi_id');
    }
}
