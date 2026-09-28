<?php

namespace App\Http\Controllers;

use App\Models\Instansi;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Builder;

abstract class Controller
{
    /** Batasi query ke instansi user bila user adalah operator instansi. */
    protected function scoped(Builder $query, string $column = 'instansi_id'): Builder
    {
        $user = request()->user();

        return $query->when($user?->isScopedToInstansi(), fn ($q) => $q->where($column, $user->instansi_id));
    }

    protected function authorizeInstansi(?int $instansiId): void
    {
        abort_unless(request()->user()?->canAccessInstansi($instansiId), 403, 'Data ini bukan milik instansi Anda.');
    }

    /** Instansi yang boleh dipilih user pada form/filter. */
    protected function instansiOptions()
    {
        return $this->scoped(Instansi::query(), 'id')
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama']);
    }

    /**
     * ID unit (beserta sub-unit) dari filter ?unit_kerja_id=, setelah memastikan unit tersebut
     * boleh diakses user dan termasuk instansi yang sedang dipilih. Null bila tidak ada filter unit.
     */
    protected function unitFilterIds(?int $instansiId): ?array
    {
        if (! request()->filled('unit_kerja_id')) {
            return null;
        }

        $unit = UnitKerja::find(request()->integer('unit_kerja_id'));
        abort_unless($unit, 404, 'Unit kerja tidak ditemukan.');
        $this->authorizeInstansi($unit->instansi_id);
        abort_if($instansiId && (int) $unit->instansi_id !== $instansiId, 404, 'Unit kerja tidak termasuk instansi yang dipilih.');

        return UnitKerja::descendantIds($unit->id);
    }

    /** Instansi aktif dari query string atau instansi user operator. */
    protected function selectedInstansiId(): ?int
    {
        $user = request()->user();
        if ($user?->isScopedToInstansi()) {
            return $user->instansi_id;
        }

        return request()->integer('instansi_id') ?: null;
    }
}
