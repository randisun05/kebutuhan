<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rekomendasi BKN dan penetapan KemenPANRB untuk satu instansi × jabatan pokok JF MASN
 * (diimpor dari Excel rekap tim data). Rincian per jenjang ada di rekomendasi_jf_jenjang.
 */
class RekomendasiJf extends Model
{
    protected $table = 'rekomendasi_jf';

    protected $guarded = [];

    protected $casts = ['ditetapkan_sesuai_bezetting' => 'boolean'];

    public const JABATAN = [
        'Analis SDM Aparatur' => 'keahlian',
        'Pranata SDM Aparatur' => 'keterampilan',
        'Asesor SDM Aparatur' => 'keahlian',
        'Auditor Manajemen ASN' => 'keahlian',
    ];

    /** Urutan jenjang sesuai empat kolom Excel (Pertama/Terampil, Muda/Mahir, Madya/Penyelia, Utama). */
    public const JENJANG = [
        'keahlian' => ['pertama', 'muda', 'madya', 'utama'],
        'keterampilan' => ['terampil', 'mahir', 'penyelia', null], // Pranata tidak punya jenjang keempat
    ];

    public function refInstansi(): BelongsTo
    {
        return $this->belongsTo(RefInstansiBkn::class, 'ref_instansi_id');
    }

    public function jenjangs(): HasMany
    {
        return $this->hasMany(RekomendasiJfJenjang::class);
    }

    public function surats(): HasMany
    {
        return $this->hasMany(RekomendasiJfSurat::class)->orderBy('jenis')->orderBy('urutan');
    }
}
