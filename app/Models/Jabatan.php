<?php

namespace App\Models;

use App\Support\Referensi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Jabatan extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('master');
    }

    protected $fillable = [
        'kode', 'siasn_jabatan_id', 'nama', 'jenis', 'kategori', 'jenjang', 'kelas_jabatan', 'bup', 'kualifikasi_pendidikan', 'estimasi_biaya_tahunan', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected $attributes = ['is_active' => true, 'bup' => 58];

    protected $appends = ['jenis_label'];

    public function getJenisLabelAttribute(): ?string
    {
        // kolom jenis bisa tidak ikut dimuat (select sebagian kolom)
        return $this->jenis ? (Referensi::JENIS_JABATAN[$this->jenis] ?? $this->jenis) : null;
    }
}
