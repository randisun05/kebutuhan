<?php

namespace App\Models;

use App\Support\Referensi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AnjabAbk extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'instansi_id', 'unit_kerja_id', 'jabatan_id', 'tahun', 'ikhtisar_jabatan', 'informasi', 'kelas_jabatan',
        'waktu_kerja_efektif', 'pertumbuhan_beban', 'finalized_by', 'finalized_at', 'total_beban_kerja', 'kebutuhan_hitung', 'kebutuhan', 'status', 'created_by',
    ];

    protected $attributes = ['status' => 'draft', 'waktu_kerja_efektif' => 75000];

    protected $casts = [
        'total_beban_kerja' => 'float',
        'kebutuhan_hitung' => 'float',
        'pertumbuhan_beban' => 'float',
        'informasi' => 'array',
        'finalized_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['tahun', 'status', 'kebutuhan', 'total_beban_kerja', 'waktu_kerja_efektif', 'pertumbuhan_beban'])
            ->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('abk');
    }

    /**
     * Proyeksi kebutuhan pada tahun ke-n: beban kerja tumbuh sebesar
     * pertumbuhan_beban % per tahun (majemuk) dari tahun ABK.
     */
    public function kebutuhanPadaTahun(int $tahun): int
    {
        $n = max(0, $tahun - $this->tahun);
        $beban = $this->total_beban_kerja * ((1 + $this->pertumbuhan_beban / 100) ** $n);
        $wke = $this->waktu_kerja_efektif ?: Referensi::WAKTU_KERJA_EFEKTIF;

        return (int) round($beban / $wke, 0, PHP_ROUND_HALF_UP);
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function uraianTugas(): HasMany
    {
        return $this->hasMany(AnjabUraianTugas::class)->orderBy('urutan');
    }

    /**
     * Hitung ulang beban kerja & kebutuhan pegawai dari uraian tugas.
     *
     * Kebutuhan = Σ (volume tahunan × norma waktu) / waktu kerja efektif,
     * dibulatkan: pecahan ≥ 0,5 ke atas. Volume per bulan/minggu/hari disetahunkan
     * dengan rasio jam kerja efektif (lihat Referensi::PERIODE_PER_TAHUN).
     */
    public function recalculate(): void
    {
        $wke = $this->waktu_kerja_efektif ?: Referensi::WAKTU_KERJA_EFEKTIF;
        $total = $this->uraianTugas()->get()->sum(fn (AnjabUraianTugas $t) => $t->bebanKerja());

        $this->total_beban_kerja = $total;
        $this->kebutuhan_hitung = round($total / $wke, 2);
        $this->kebutuhan = (int) round($total / $wke, 0, PHP_ROUND_HALF_UP);
        $this->save();
    }
}
