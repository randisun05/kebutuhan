<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Tata letak berkas Excel sumber (sheet, kolom, blok angka) sebagai data yang dapat diubah tanpa mengubah kode. */
class ImporProfil extends Model
{
    use LogsActivity;

    protected $table = 'impor_profil';

    protected $guarded = [];

    protected $casts = ['pemetaan' => 'array', 'is_active' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('master');
    }

    public function pembina(): BelongsTo
    {
        return $this->belongsTo(InstansiPembina::class, 'pembina_id');
    }
}
