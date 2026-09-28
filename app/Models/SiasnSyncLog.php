<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiasnSyncLog extends Model
{
    protected $fillable = ['instansi_id', 'user_id', 'jenis', 'status', 'total', 'berhasil', 'gagal', 'pesan', 'started_at', 'finished_at'];

    protected $casts = [
        'pesan' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
