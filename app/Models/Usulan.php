<?php

namespace App\Models;

use App\Enums\UsulanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Usulan extends Model
{
    use HasFactory;

    protected $fillable = [
        'nomor', 'instansi_id', 'tahun', 'periode', 'jenis_asn', 'perihal', 'keterangan',
        'surat_pengantar', 'status', 'catatan_terakhir', 'diajukan_at', 'ditetapkan_at', 'created_by',
    ];

    protected $casts = [
        'status' => UsulanStatus::class,
        'diajukan_at' => 'datetime',
        'ditetapkan_at' => 'datetime',
    ];

    protected $appends = ['status_label', 'status_color'];

    protected static function booted(): void
    {
        static::creating(function (Usulan $usulan) {
            if (! $usulan->nomor) {
                $usulan->nomor = static::generateNomor($usulan->instansi_id, $usulan->tahun);
            }
        });
    }

    public static function generateNomor(int $instansiId, int $tahun): string
    {
        $kode = Instansi::whereKey($instansiId)->value('kode') ?? $instansiId;
        $prefix = sprintf('USL/%s/%d/', $kode, $tahun);
        $last = static::where('nomor', 'like', $prefix.'%')->count();

        do {
            $last++;
            $nomor = $prefix.str_pad((string) $last, 4, '0', STR_PAD_LEFT);
        } while (static::where('nomor', $nomor)->exists());

        return $nomor;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? '-';
    }

    public function getStatusColorAttribute(): string
    {
        return $this->status?->color() ?? 'secondary';
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(UsulanDetail::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(UsulanLog::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function penetapan(): HasOne
    {
        return $this->hasOne(Penetapan::class);
    }
}
