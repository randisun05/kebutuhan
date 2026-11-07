<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Satu kali pengisian massal kebutuhan sementara (dapat dibatalkan). */
class KebutuhanSementaraBatch extends Model
{
    protected $table = 'kebutuhan_sementara_batches';

    protected $fillable = ['uuid', 'instansi_id', 'user_id', 'sumber', 'jumlah_dibuat', 'dibatalkan_at'];

    protected $casts = ['dibatalkan_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
