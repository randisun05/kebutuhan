<?php

namespace App\Models;

use App\Support\Referensi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnjabAbk extends Model
{
    use HasFactory;

    protected $fillable = [
        'instansi_id', 'unit_kerja_id', 'jabatan_id', 'tahun', 'ikhtisar_jabatan',
        'waktu_kerja_efektif', 'total_beban_kerja', 'kebutuhan_hitung', 'kebutuhan', 'status', 'created_by',
    ];

    protected $attributes = ['status' => 'draft', 'waktu_kerja_efektif' => 75000];

    protected $casts = [
        'total_beban_kerja' => 'float',
        'kebutuhan_hitung' => 'float',
    ];

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
     * Kebutuhan = Σ (volume × norma waktu) / waktu kerja efektif,
     * dibulatkan: pecahan ≥ 0,5 ke atas.
     */
    public function recalculate(): void
    {
        $wke = $this->waktu_kerja_efektif ?: Referensi::WAKTU_KERJA_EFEKTIF;
        $total = $this->uraianTugas()->get()->sum(fn ($t) => $t->volume * $t->norma_waktu);

        $this->total_beban_kerja = $total;
        $this->kebutuhan_hitung = round($total / $wke, 2);
        $this->kebutuhan = (int) round($total / $wke, 0, PHP_ROUND_HALF_UP);
        $this->save();
    }
}
