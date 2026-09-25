<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\PegawaiRiwayat;
use App\Models\UnitKerja;
use App\Services\HistoriService;
use Maatwebsite\Excel\Facades\Excel;

/** Histori perubahan data existing & analisis yang tidak bergerak. */
class HistoriController extends Controller
{
    public function __construct(private HistoriService $histori) {}

    public function index()
    {
        [$filters, $instansiId] = $this->filters();
        $level = in_array(request('level'), HistoriService::LEVEL, true) ? request('level') : ($instansiId ? 'unit' : 'instansi');
        $bulan = $this->bulan();
        $hanyaDiam = request('tampil', 'diam') === 'diam';

        return inertia('Histori/Index', [
            'filters' => [
                'instansi_id' => $instansiId, 'unit_kerja_id' => request('unit_kerja_id'), 'level' => $level,
                'bulan' => $bulan, 'tampil' => $hanyaDiam ? 'diam' : 'semua', 'jenis' => request('jenis'),
            ],
            'instansiOptions' => $this->instansiOptions(),
            'unitOptions' => $instansiId ? UnitKerja::flatTree($instansiId) : [],
            'jenisRiwayat' => PegawaiRiwayat::JENIS,
            'tren' => fn () => $this->histori->tren($filters, 12),
            'pergerakan' => fn () => $this->histori->pergerakanBulanan($filters, 12),
            'analisis' => fn () => $this->histori->analisis($level, $filters, $bulan, $hanyaDiam)->take(500),
            'ringkasan' => fn () => $this->ringkasan($level, $filters, $bulan),
            'log' => fn () => $this->histori->log($filters, request('jenis'), now()->subMonths($bulan))->paginate(20)->withQueryString(),
        ]);
    }

    public function export()
    {
        [$filters, $instansiId] = $this->filters();
        $level = in_array(request('level'), HistoriService::LEVEL, true) ? request('level') : ($instansiId ? 'unit' : 'instansi');
        $bulan = $this->bulan();
        $rows = $this->histori->analisis($level, $filters, $bulan, request('tampil', 'diam') === 'diam');

        return Excel::download(new ArrayExport(
            ['Nama', 'Induk', 'Kebutuhan', "Existing {$bulan} bln lalu", 'Existing sekarang', 'Perubahan', 'Selisih thd kebutuhan', "Pergerakan {$bulan} bln", 'Terakhir bergerak', 'Kondisi'],
            $rows->map(fn ($r) => [$r['nama'], $r['induk'], $r['kebutuhan'], $r['existing_awal'], $r['existing'], $r['perubahan'], $r['selisih'],
                $r['pergerakan'], $r['terakhir_bergerak'], $r['kondisi']])->all()
        ), "histori-existing-{$level}-{$bulan}bln-".now()->format('Ymd').'.xlsx');
    }

    private function ringkasan(string $level, array $filters, int $bulan): array
    {
        $semua = $this->histori->analisis($level, $filters, $bulan, false);
        $diam = $semua->where('pergerakan', 0);

        return [
            'total' => $semua->count(),
            'diam' => $diam->count(),
            'diam_bermasalah' => $diam->whereIn('kondisi', ['kurang', 'kosong', 'lebih'])->count(),
            'diam_kurang' => $diam->whereIn('kondisi', ['kurang', 'kosong'])->count(),
            'total_pergerakan' => $semua->sum('pergerakan'),
        ];
    }

    private function filters(): array
    {
        $instansiId = $this->selectedInstansiId();
        if ($instansiId) {
            $this->authorizeInstansi($instansiId);
        }

        return [array_filter([
            'instansi_id' => $instansiId,
            'unit_ids' => request('unit_kerja_id') ? UnitKerja::descendantIds(request()->integer('unit_kerja_id')) : null,
        ]), $instansiId];
    }

    private function bulan(): int
    {
        return in_array(request()->integer('bulan'), [1, 3, 6, 12, 24], true) ? request()->integer('bulan') : 6;
    }
}
