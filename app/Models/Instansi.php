<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Instansi extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('master');
    }

    protected $fillable = ['kode', 'siasn_instansi_id', 'siasn_satuan_kerja_id', 'nama', 'jenis', 'provinsi', 'alamat', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected $attributes = ['is_active' => true, 'jenis' => 'pusat'];

    public function unitKerjas(): HasMany
    {
        return $this->hasMany(UnitKerja::class);
    }

    public function pegawais(): HasMany
    {
        return $this->hasMany(Pegawai::class);
    }

    public function usulans(): HasMany
    {
        return $this->hasMany(Usulan::class);
    }
}
