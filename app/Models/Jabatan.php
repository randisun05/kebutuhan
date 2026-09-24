<?php

namespace App\Models;

use App\Support\Referensi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jabatan extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode', 'nama', 'jenis', 'kategori', 'jenjang', 'kelas_jabatan', 'bup', 'kualifikasi_pendidikan', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected $attributes = ['is_active' => true, 'bup' => 58];

    protected $appends = ['jenis_label'];

    public function getJenisLabelAttribute(): string
    {
        return Referensi::JENIS_JABATAN[$this->jenis] ?? $this->jenis;
    }
}
