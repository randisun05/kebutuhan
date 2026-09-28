<?php

namespace App\Http\Controllers;

use App\Exports\MonitoringExport;
use App\Models\Instansi;
use App\Models\UnitKerja;
use App\Services\MonitoringService;
use App\Support\Referensi;
use Maatwebsite\Excel\Facades\Excel;

class MonitoringController extends Controller
{
    public function __construct(private MonitoringService $monitoring) {}

    /** Rekap seluruh instansi: mana yang kekurangan (kosong) dan kelebihan (gemuk). */
    public function index()
    {
        $user = request()->user();
        if ($user->isScopedToInstansi()) {
            return redirect()->route('monitoring.instansi', $user->instansi_id);
        }

        $filters = $this->filters();
        $rows = $this->monitoring->byInstansi($filters);

        $sort = request('sort', 'kurang');
        $rows = in_array($sort, ['kurang', 'lebih', 'kebutuhan', 'existing', 'jabatan_kosong', 'persentase'], true)
            ? $rows->sortByDesc(fn ($r) => $r[$sort] ?? -1)->values()
            : $rows;

        return inertia('Monitoring/Index', [
            'filters' => $filters + ['sort' => $sort],
            'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN),
            'summary' => fn () => $this->monitoring->summary($filters),
            'rows' => fn () => $rows,
            'updatedAt' => fn () => now()->toIso8601String(),
        ]);
    }

    /** Breakdown satu instansi per unit kerja (hierarki) dan per jenis jabatan. */
    public function instansi(Instansi $instansi)
    {
        $this->authorizeInstansi($instansi->id);
        $filters = $this->filters();

        return inertia('Monitoring/Instansi', [
            'instansi' => $instansi->only(['id', 'kode', 'nama', 'jenis']),
            'filters' => $filters,
            'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN),
            'summary' => fn () => $this->monitoring->summary($filters + ['instansi_id' => $instansi->id]),
            'units' => fn () => $this->monitoring->byUnit($instansi->id, $filters),
            'perJenis' => fn () => $this->monitoring->byJenisJabatan($filters + ['instansi_id' => $instansi->id]),
            'updatedAt' => fn () => now()->toIso8601String(),
        ]);
    }

    /** Rincian per jabatan pada satu unit kerja (opsional termasuk sub-unit). */
    public function unit(UnitKerja $unit)
    {
        $this->authorizeInstansi($unit->instansi_id);

        $withChildren = request()->boolean('sub', true);
        $filters = $this->filters() + [
            'instansi_id' => $unit->instansi_id,
            'unit_ids' => $withChildren ? UnitKerja::descendantIds($unit->id) : [$unit->id],
        ];

        return inertia('Monitoring/Unit', [
            'unit' => $unit->load('instansi:id,nama', 'parent:id,nama')->only(['id', 'nama', 'kode', 'eselon', 'instansi', 'parent', 'instansi_id']),
            'filters' => array_diff_key($filters, ['unit_ids' => 1, 'instansi_id' => 1]) + ['sub' => $withChildren],
            'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN),
            'summary' => fn () => $this->monitoring->summary($filters),
            'positions' => fn () => $this->monitoring->positions($filters, 2000),
            'updatedAt' => fn () => now()->toIso8601String(),
        ]);
    }

    public function export()
    {
        $filters = $this->filters();
        if ($id = $this->selectedInstansiId()) {
            $filters['instansi_id'] = $id;
        }

        return Excel::download(
            new MonitoringExport($this->monitoring->positions($filters, 100000)),
            'monitoring-kebutuhan-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function filters(): array
    {
        return array_filter([
            'jenis' => request('jenis'),
            'status' => request('status'),
            'q' => request('q'),
        ]);
    }
}
