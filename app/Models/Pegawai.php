<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Pegawai extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['nama', 'unit_kerja_id', 'jabatan_id', 'golongan', 'is_active'])
            ->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('pegawai');
    }

    protected $fillable = [
        'instansi_id', 'unit_kerja_id', 'jabatan_id', 'nip', 'nama', 'status_kepegawaian',
        'golongan', 'pendidikan', 'tanggal_lahir', 'tmt_jabatan', 'is_active',
        'siasn_id', 'sumber', 'siasn_synced_at',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date:Y-m-d',
        'tmt_jabatan' => 'date:Y-m-d',
        'tmt_pensiun' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'siasn_synced_at' => 'datetime',
    ];

    protected $attributes = ['is_active' => true, 'status_kepegawaian' => 'pns', 'sumber' => 'manual'];

    /** Keterangan riwayat untuk perubahan berikutnya (diisi controller/sinkronisasi). */
    public ?string $keteranganRiwayat = null;

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

        // catat riwayat mutasi unit, pergantian jabatan, dan perubahan status aktif
        static::created(fn (Pegawai $p) => $p->catatRiwayat('masuk', null, $p->unit_kerja_id, null, $p->jabatan_id));
        static::updated(function (Pegawai $p) {
            $unitBerubah = $p->wasChanged('unit_kerja_id');
            $jabatanBerubah = $p->wasChanged('jabatan_id');

            if ($unitBerubah || $jabatanBerubah) {
                $p->catatRiwayat(
                    $unitBerubah ? 'mutasi_unit' : 'ganti_jabatan',
                    $p->getOriginal('unit_kerja_id'), $p->unit_kerja_id,
                    $p->getOriginal('jabatan_id'), $p->jabatan_id,
                );
            }
            if ($p->wasChanged('is_active')) {
                $p->catatRiwayat($p->is_active ? 'masuk' : 'keluar', $p->unit_kerja_id, $p->unit_kerja_id, $p->jabatan_id, $p->jabatan_id);
            }
        });
    }

    public function catatRiwayat(string $jenis, $dariUnit, $keUnit, $dariJabatan, $keJabatan): void
    {
        $this->riwayats()->create([
            'jenis' => $jenis,
            'dari_unit_id' => $dariUnit, 'ke_unit_id' => $keUnit,
            'dari_jabatan_id' => $dariJabatan, 'ke_jabatan_id' => $keJabatan,
            'keterangan' => $this->keteranganRiwayat,
            'sumber' => $this->sumber ?? 'manual',
            'user_id' => auth()->id(),
        ]);
    }

    public function riwayats(): HasMany
    {
        return $this->hasMany(PegawaiRiwayat::class)->latest('created_at')->latest('id');
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
