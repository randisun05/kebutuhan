<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsulanDetail extends Model
{
    protected $fillable = [
        'usulan_id', 'unit_kerja_id', 'jabatan_id', 'kebutuhan_abk', 'sumber_kebutuhan', 'existing', 'proyeksi_pensiun',
        'formasi_belum_terisi', 'usulan_proses', 'redistribusi_masuk',
        'jumlah_usul', 'prioritas', 'jumlah_rekomendasi', 'jumlah_ditetapkan', 'jumlah_terisi', 'terisi_updated_at', 'kualifikasi_pendidikan', 'keterangan',
    ];

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class);
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    /** Pegawai yang diangkat dari formasi ini (opsional; pengisian juga boleh dicatat manual). */
    public function pegawais(): HasMany
    {
        return $this->hasMany(Pegawai::class);
    }

    /**
     * Sesuaikan jumlah terisi setelah tautan pegawai berubah. Jumlah terisi tidak pernah kurang dari
     * pegawai yang ditautkan, dan pengisian manual (tanpa tautan) tetap dihormati.
     */
    public function sesuaikanTerisi(int $perubahan): void
    {
        $tertaut = $this->pegawais()->where('is_active', true)->count();
        $baru = max($tertaut, (int) $this->jumlah_terisi + $perubahan);
        $baru = min($baru, max($tertaut, (int) $this->jumlah_ditetapkan));

        if ($baru !== (int) $this->jumlah_terisi) {
            $this->update(['jumlah_terisi' => $baru, 'terisi_updated_at' => now()]);
        }
    }
}
