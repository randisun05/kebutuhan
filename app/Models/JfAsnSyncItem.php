<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Status sinkronisasi satu instansi BKN dalam satu putaran (checkpoint untuk melanjutkan putaran yang macet). */
class JfAsnSyncItem extends Model
{
    protected $table = 'jf_asn_sync_items';

    protected $guarded = [];

    public const SELESAI = ['done', 'failed'];

    public function refInstansi(): BelongsTo
    {
        return $this->belongsTo(RefInstansiBkn::class, 'ref_instansi_id');
    }
}
