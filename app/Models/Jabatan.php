<?php

namespace App\Models;

use App\Support\Referensi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'kode', 'siasn_jabatan_id', 'nama', 'jenis', 'kategori', 'jenjang', 'kelas_jabatan', 'bup', 'kualifikasi_pendidikan', 'estimasi_biaya_tahunan', 'jf_jabatan_pokok_id', 'jf_jenjang_id', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected $attributes = ['is_active' => true, 'bup' => 58];

    protected $appends = ['jenis_label'];

    public function getJenisLabelAttribute(): ?string
    {
        // kolom jenis bisa tidak ikut dimuat (select sebagian kolom)
        return $this->jenis ? (Referensi::JENIS_JABATAN[$this->jenis] ?? $this->jenis) : null;
    }

    /** Jabatan pokok JF MASN yang dikaitkan (opsional), mis. "Analis SDM Aparatur" untuk jabatan jenjang Ahli Pertama. */
    public function jfJabatanPokok(): BelongsTo
    {
        return $this->belongsTo(JfJabatanPokok::class, 'jf_jabatan_pokok_id');
    }

    public function jfJenjang(): BelongsTo
    {
        return $this->belongsTo(JfJenjang::class, 'jf_jenjang_id');
    }
}
