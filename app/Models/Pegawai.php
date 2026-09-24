<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Pegawai extends Model
{
    use HasFactory;

    protected $fillable = [
        'instansi_id', 'unit_kerja_id', 'jabatan_id', 'nip', 'nama', 'status_kepegawaian',
        'golongan', 'pendidikan', 'tanggal_lahir', 'tmt_jabatan', 'is_active',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date:Y-m-d',
        'tmt_jabatan' => 'date:Y-m-d',
        'tmt_pensiun' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    protected $attributes = ['is_active' => true, 'status_kepegawaian' => 'pns'];

    protected static function booted(): void
    {
        // TMT pensiun = hari pertama bulan berikutnya setelah mencapai BUP jabatan
        static::saving(function (Pegawai $pegawai) {
            if ($pegawai->tanggal_lahir && $pegawai->jabatan_id) {
                $bup = Jabatan::whereKey($pegawai->jabatan_id)->value('bup') ?: 58;
                $pegawai->tmt_pensiun = Carbon::parse($pegawai->tanggal_lahir)
                    ->addYears($bup)->startOfMonth()->addMonth();
            } else {
                $pegawai->tmt_pensiun = null;
            }
        });
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
}
