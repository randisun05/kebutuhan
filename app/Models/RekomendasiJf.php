<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Status rekomendasi/penetapan untuk satu instansi (alias) × jabatan pokok JF MASN.
 * Angkanya ada di kebutuhan_fakta (per jenjang dan sumber, berversi); suratnya di rekomendasi_jf_surat.
 */
class RekomendasiJf extends Model
{
    protected $table = 'rekomendasi_jf';

    protected $guarded = [];

    protected $casts = ['ditetapkan_sesuai_bezetting' => 'boolean'];

    public function alias(): BelongsTo
    {
        return $this->belongsTo(InstansiAlias::class, 'alias_id');
    }

    public function jabatanPokok(): BelongsTo
    {
        return $this->belongsTo(JfJabatanPokok::class, 'jabatan_pokok_id');
    }

    public function fakta(): HasMany
    {
        return $this->hasMany(KebutuhanFakta::class, 'rekomendasi_jf_id');
    }

    /** Hanya nilai yang berlaku (bukan riwayat). */
    public function faktaTerbaru(): HasMany
    {
        return $this->fakta()->where('is_terbaru', true);
    }

    public function surats(): HasMany
    {
        return $this->hasMany(RekomendasiJfSurat::class)->orderBy('jenis')->orderBy('urutan');
    }

    /** Hanya rekomendasi untuk JF milik pembina tertentu. */
    public function scopeUntukPembina($query, ?int $pembinaId)
    {
        return $pembinaId === null ? $query : $query->whereHas('jabatanPokok', fn ($q) => $q->where('pembina_id', $pembinaId));
    }
}
