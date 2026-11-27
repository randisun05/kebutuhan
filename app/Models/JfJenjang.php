<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Jenjang JF (mis. Pertama, Muda, Terampil). Master yang dikelola lewat menu manajemen. */
class JfJenjang extends Model
{
    use LogsActivity;

    protected $table = 'jf_jenjang';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('master');
    }
}
