<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    /**
     * Asal nilai; setiap pilihan di sini sudah berfungsi (opsi baru ditambahkan bersama implementasinya).
     * fakta = angka dari impor Excel (tabel kebutuhan_fakta); abk dan pegawai = dihitung langsung dari data SIMONKEB lewat kaitan
     * jabatan ke JF; dashboard = ditarik dari API Dashboard ke tabel kebutuhan_fakta (perintah jf:tarik-dashboard).
     */
    public const ADAPTER = [
        'fakta' => 'Impor (tabel fakta)',
        'abk' => 'ABK SIMONKEB (dihitung langsung)',
        'pegawai' => 'Pegawai SIMONKEB (dihitung langsung)',
        'dashboard' => 'API Dashboard (ditarik berkala)',
    ];

    /** Jenis yang wajib untuk adapter tertentu; adapter selain fakta hanya boleh satu sumber per pembina. */
    public const JENIS_ADAPTER = ['abk' => 'kebutuhan', 'pegawai' => 'bezetting', 'dashboard' => 'bezetting'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()->useLogName('master');
    }

    public function pembina(): BelongsTo
    {
        return $this->belongsTo(InstansiPembina::class, 'pembina_id');
    }
}
