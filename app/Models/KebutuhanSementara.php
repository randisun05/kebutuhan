<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Kebutuhan pegawai per posisi yang dipakai selama posisi tersebut belum memiliki ABK final. */
class KebutuhanSementara extends Model
{
    use LogsActivity;

    protected $table = 'kebutuhan_sementara';

    protected $fillable = ['instansi_id', 'unit_kerja_id', 'jabatan_id', 'jumlah', 'dasar', 'created_by'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['unit_kerja_id', 'jabatan_id', 'jumlah', 'dasar'])
            ->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('abk');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }
}
