<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class UnitKerja extends Model
{
    use HasFactory;

    protected $fillable = ['instansi_id', 'parent_id', 'kode', 'nama', 'eselon', 'urutan'];

    public function instansi(): BelongsTo
    {
        return $this->belongsTo(Instansi::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('urutan')->orderBy('nama');
    }

    public function pegawais(): HasMany
    {
        return $this->hasMany(Pegawai::class);
    }

    /**
     * Urutkan unit kerja satu instansi menjadi daftar datar ber-indentasi (depth)
     * sesuai hierarki organisasi.
     *
     * @return Collection<int, array{id:int, nama:string, depth:int, parent_id:?int}>
     */
    public static function flatTree(int $instansiId): Collection
    {
        $units = static::where('instansi_id', $instansiId)
            ->orderBy('urutan')->orderBy('nama')
            ->get(['id', 'parent_id', 'kode', 'nama', 'eselon']);

        $byParent = $units->groupBy(fn ($u) => $u->parent_id ?? 0);
        $ids = $units->pluck('id')->flip();
        $result = collect();

        $walk = function ($parentId, $depth) use (&$walk, $byParent, $result) {
            foreach ($byParent->get($parentId, []) as $unit) {
                $result->push([
                    'id' => $unit->id,
                    'parent_id' => $unit->parent_id,
                    'kode' => $unit->kode,
                    'nama' => $unit->nama,
                    'eselon' => $unit->eselon,
                    'depth' => $depth,
                ]);
                $walk($unit->id, $depth + 1);
            }
        };

        $walk(0, 0);

        // unit yang induknya berada di luar instansi / terhapus tetap ditampilkan
        foreach ($units as $unit) {
            if ($unit->parent_id && ! $ids->has($unit->parent_id) && ! $result->contains('id', $unit->id)) {
                $result->push(['id' => $unit->id, 'parent_id' => null, 'kode' => $unit->kode, 'nama' => $unit->nama, 'eselon' => $unit->eselon, 'depth' => 0]);
                $walk($unit->id, 1);
            }
        }

        return $result;
    }

    /** ID unit ini beserta seluruh sub-unit di bawahnya. */
    public static function descendantIds(int $unitId): array
    {
        $ids = [$unitId];
        $frontier = [$unitId];

        while ($frontier) {
            $frontier = static::whereIn('parent_id', $frontier)->pluck('id')->all();
            $frontier = array_diff($frontier, $ids);
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
