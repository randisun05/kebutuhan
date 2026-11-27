<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Jabatan pokok JF MASN (mis. Analis SDM Aparatur). Master yang dikelola lewat menu manajemen. */
class JfJabatanPokok extends Model
{
    use LogsActivity;

    protected $table = 'jf_jabatan_pokok';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public const RUMPUN = ['keahlian' => 'Keahlian', 'keterampilan' => 'Keterampilan'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('master');
    }

    /** Jenjang yang berlaku, berurutan menurut posisi kolom pada blok angka Excel. */
    public function jenjangs(): BelongsToMany
    {
        return $this->belongsToMany(JfJenjang::class, 'jf_jabatan_jenjang', 'jabatan_pokok_id', 'jenjang_id')
            ->withPivot('urutan_kolom')->withTimestamps()->orderByPivot('urutan_kolom');
    }

    public function rekomendasis(): HasMany
    {
        return $this->hasMany(RekomendasiJf::class, 'jabatan_pokok_id');
    }
}
