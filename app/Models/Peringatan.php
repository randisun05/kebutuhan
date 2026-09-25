<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Peringatan extends Model
{
    public const TINGKAT = ['kritis' => 1, 'tinggi' => 2, 'sedang' => 3, 'rendah' => 4];

    protected $fillable = [
        'kode', 'tingkat', 'instansi_id', 'unit_kerja_id', 'jabatan_id', 'kunci', 'judul', 'pesan', 'data', 'url', 'status',
        'catatan_tindak_lanjut', 'ditangani_oleh', 'pertama_terdeteksi_at', 'terakhir_terdeteksi_at', 'selesai_at',
    ];

    protected $casts = [
        'data' => 'array',
        'pertama_terdeteksi_at' => 'datetime',
        'terakhir_terdeteksi_at' => 'datetime',
        'selesai_at' => 'datetime',
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

    public function penangan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditangani_oleh');
    }
}
