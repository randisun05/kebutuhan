<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Nama instansi sebagaimana tertulis di sumber data. Beberapa alias dapat menuju satu referensi instansi BKN,
 * sehingga instansi yang berganti nama atau ditulis berbeda tidak memutus data.
 */
class InstansiAlias extends Model
{
    use LogsActivity;

    protected $table = 'instansi_alias';

    protected $guarded = [];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['nama', 'ref_instansi_id', 'pokja', 'wilayah_kerja'])->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('master');
    }

    public static function normalisasi(?string $nama): string
    {
        return RefInstansiBkn::normalisasiNama($nama);
    }

    public function refInstansi(): BelongsTo
    {
        return $this->belongsTo(RefInstansiBkn::class, 'ref_instansi_id');
    }

    public function rekomendasis(): HasMany
    {
        return $this->hasMany(RekomendasiJf::class, 'alias_id');
    }
}
