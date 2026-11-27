<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Instansi pembina JF (mis. BKN untuk JF SDM Aparatur). Memiliki jabatan pokok, sumber angka, dan profil impor sendiri;
 * penggunanya (peran pembina) hanya mengakses data haknya.
 */
class InstansiPembina extends Model
{
    use LogsActivity;

    protected $table = 'instansi_pembina';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('master');
    }

    public function jabatanPokok(): HasMany
    {
        return $this->hasMany(JfJabatanPokok::class, 'pembina_id');
    }

    public function sumber(): HasMany
    {
        return $this->hasMany(SumberData::class, 'pembina_id');
    }

    public function profil(): HasMany
    {
        return $this->hasMany(ImporProfil::class, 'pembina_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'pembina_id');
    }
}
