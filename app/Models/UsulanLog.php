<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsulanLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['usulan_id', 'user_id', 'aksi', 'dari_status', 'ke_status', 'catatan'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
