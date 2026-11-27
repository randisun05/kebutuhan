<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Sumber angka kebutuhan/bezetting (rekom BKN, penetapan Menpan, bezetting, ABK, ...). Sumber baru = baris baru. */
class SumberData extends Model
{
    use LogsActivity;

    protected $table = 'sumber_data';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public const JENIS = ['kebutuhan' => 'Kebutuhan', 'bezetting' => 'Bezetting'];

    public const TINGKAT = ['instansi' => 'Per instansi', 'unit' => 'Per unit kerja'];

    public const SIFAT = ['foto' => 'Foto saat dokumen', 'hidup' => 'Data hidup (kondisi kini)'];

    /** Adapter penyedia nilai: fakta = tabel kebutuhan_fakta (impor); lainnya dibaca langsung dari modul terkait. */
    public const ADAPTER = ['fakta' => 'Impor (tabel fakta)', 'abk' => 'ABK SIMONKEB', 'pegawai' => 'Pegawai SIMONKEB', 'dashboard' => 'API Dashboard'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('master');
    }
}
