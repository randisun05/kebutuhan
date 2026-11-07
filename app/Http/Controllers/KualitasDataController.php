<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\UnitKerja;
use App\Services\KualitasDataService;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;

class KualitasDataController extends Controller
{
    private const PER_HALAMAN = 25;

    public function index(KualitasDataService $service)
    {
        $instansiOptions = $this->instansiOptions();
        $instansiId = $this->selectedInstansiId() ?? $instansiOptions->first()?->id;
        if ($instansiId) {
            $this->authorizeInstansi((int) $instansiId);
        }

        $pemeriksaan = $instansiId ? $service->periksa((int) $instansiId) : [];

        // rincian lengkap satu jenis pemeriksaan (dengan pagination dan filter unit)
        $rincian = null;
        if ($instansiId && ($kode = request('kode'))) {
            $r = $service->rincian((int) $instansiId, (string) $kode, request()->integer('unit_id') ?: null);
            if ($r) {
                $halaman = max(1, request()->integer('page', 1));
                $r['temuan'] = (new LengthAwarePaginator(
                    $r['temuan']->forPage($halaman, self::PER_HALAMAN)->values(), $r['temuan']->count(), self::PER_HALAMAN, $halaman,
                    ['path' => request()->url(), 'query' => request()->except('page')]
                ))->toArray();
                $rincian = $r;
            }
        }

        return inertia('KualitasData/Index', [
            'instansiOptions' => $instansiOptions,
            'instansiId' => $instansiId,
            'pemeriksaan' => $pemeriksaan,
            'totalTemuan' => collect($pemeriksaan)->sum('jumlah'),
            'rincian' => $rincian,
            'filters' => ['kode' => request('kode'), 'unit_id' => request('unit_id')],
            'unitOptions' => $instansiId && $rincian ? UnitKerja::flatTree((int) $instansiId) : [],
        ]);
    }

    /** Unduh seluruh temuan kualitas data satu instansi ke Excel. */
    public function export(KualitasDataService $service)
    {
        $instansiId = $this->selectedInstansiId() ?? $this->instansiOptions()->first()?->id;
        abort_unless($instansiId, 404);
        $this->authorizeInstansi((int) $instansiId);

        return Excel::download(
            new ArrayExport(['Pemeriksaan', 'Tingkat', 'NIP', 'Nama', 'Unit kerja', 'Keterangan', 'Saran perbaikan'], $service->semuaTemuan((int) $instansiId)),
            'kualitas-data-'.now()->format('Ymd-His').'.xlsx'
        );
    }
}
