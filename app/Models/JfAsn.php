<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ASN JF bidang manajemen ASN dari API instansi pembina BKN: data eksisting (bezetting) pembanding. */
class JfAsn extends Model
{
    protected $table = 'jf_asn';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'tmt_jabatan' => 'date:Y-m-d',
        'detail_synced_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function refInstansi(): BelongsTo
    {
        return $this->belongsTo(RefInstansiBkn::class, 'ref_instansi_id');
    }

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class);
    }

    /** ASN aktif yang detail jabatannya perlu diambil: belum pernah atau sudah usang, dan belum kehabisan percobaan. */
    public function scopeButuhDetail($query)
    {
        return $query->where('is_active', true)
            ->where('source', 'bkn')
            ->where('detail_attempts', '<', config('jfpembina.max_attempts'))
            ->where(fn ($w) => $w->whereNull('detail_synced_at')
                ->orWhere('detail_synced_at', '<', now()->subDays(config('jfpembina.detail_stale_days'))));
    }

    /**
     * "Analis Sumber Daya Manusia Aparatur Ahli Madya" => ['Analis Sumber Daya Manusia Aparatur', 'Ahli Madya'].
     * Jenjang dikenali dari akhiran nama jabatan; tidak dikenali => jenjang null.
     *
     * @return array{0: string, 1: ?string}
     */
    public static function pisahJabatan(?string $nama): array
    {
        $nama = trim((string) $nama);
        if ($nama !== '' && preg_match('/^(.*?)\s+(Ahli (?:Pertama|Muda|Madya|Utama)|Terampil|Mahir|Penyelia|Pelaksana(?: Lanjutan)?)$/i', $nama, $m)) {
            return [trim($m[1]), ucwords(strtolower($m[2]))];
        }

        return [$nama, null];
    }
}
