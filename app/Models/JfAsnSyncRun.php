<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JfAsnSyncRun extends Model
{
    protected $table = 'jf_asn_sync_runs';

    protected $guarded = [];

    protected $casts = [
        'with_detail' => 'boolean',
        'failed_instansi' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
