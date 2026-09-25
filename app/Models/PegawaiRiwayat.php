<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiRiwayat extends Model
{
    public const UPDATED_AT = null;

    public const JENIS = [
        'masuk' => 'Masuk / aktif',
        'keluar' => 'Keluar / nonaktif',
        'mutasi_unit' => 'Mutasi unit kerja',
        'ganti_jabatan' => 'Pergantian jabatan',
    ];

    protected $fillable = ['pegawai_id', 'jenis', 'dari_unit_id', 'ke_unit_id', 'dari_jabatan_id', 'ke_jabatan_id', 'keterangan', 'sumber', 'user_id'];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function dariUnit(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'dari_unit_id');
    }

    public function keUnit(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class, 'ke_unit_id');
    }

    public function dariJabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'dari_jabatan_id');
    }

    public function keJabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'ke_jabatan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
