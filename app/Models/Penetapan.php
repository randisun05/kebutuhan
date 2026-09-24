<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penetapan extends Model
{
    protected $fillable = [
        'usulan_id', 'instansi_id', 'nomor_sk', 'tanggal_sk', 'tahun', 'total_ditetapkan',
        'file_sk', 'keterangan', 'ditetapkan_oleh',
    ];

    protected $casts = ['tanggal_sk' => 'date:Y-m-d'];

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(Usulan::class);
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function penetap(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }
}
