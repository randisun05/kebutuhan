<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\Instansi;
use App\Models\UnitKerja;
use App\Services\ProyeksiService;
use App\Services\RedistribusiService;
use App\Support\Referensi;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Perencanaan berbasis ABK: proyeksi kebutuhan 5 tahun dan saran redistribusi.
 */
class PerencanaanController extends Controller
{
    public function proyeksi(ProyeksiService $proyeksi)
    {
        [$instansiId, $filters] = $this->context();

        return inertia('Perencanaan/Proyeksi', [
            'instansi' => Instansi::find($instansiId, ['id', 'nama']),
            'instansiOptions' => $this->instansiOptions(),
            'unitOptions' => UnitKerja::flatTree($instansiId),
            'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN),
            'filters' => request()->only('unit_kerja_id', 'jenis', 'mulai'),
            'hasil' => $proyeksi->hitung($instansiId, $filters, request()->integer('mulai') ?: null),
        ]);
    }

    public function exportProyeksi(ProyeksiService $proyeksi)
    {
        [$instansiId, $filters] = $this->context();
        $hasil = $proyeksi->hitung($instansiId, $filters, request()->integer('mulai') ?: null);

        $head = ['Unit Kerja', 'Jabatan', 'Tahun ABK', 'Pertumbuhan %', 'Kebutuhan saat ini', 'Existing saat ini', 'Formasi belum terisi'];
        foreach ($hasil['tahun'] as $t) {
            array_push($head, "K {$t}", "B {$t}", "Pensiun s.d. {$t}", "Kurang {$t}", "Rencana formasi {$t}");
        }
        $head[] = 'Total rencana';

        $rows = array_map(function ($r) use ($hasil) {
            $row = [$r['unit_nama'], $r['jabatan_nama'], $r['tahun_abk'], $r['pertumbuhan'], $r['kebutuhan_saat_ini'], $r['existing_saat_ini'], $r['formasi_belum_terisi']];
            foreach ($hasil['tahun'] as $t) {
                $y = $r['tahun'][$t];
                array_push($row, $y['kebutuhan'], $y['existing'], $y['pensiun'], $y['kekurangan'], $y['rencana']);
            }
            $row[] = $r['total_rencana'];

            return $row;
        }, $hasil['rows']);

        return Excel::download(new ArrayExport($head, $rows), 'proyeksi-kebutuhan-'.now()->format('Ymd').'.xlsx');
    }

    public function redistribusi(RedistribusiService $redistribusi)
    {
        [$instansiId, $filters] = $this->context();

        return inertia('Perencanaan/Redistribusi', [
            'instansi' => Instansi::find($instansiId, ['id', 'nama']),
            'instansiOptions' => $this->instansiOptions(),
            'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN),
            'filters' => request()->only('jenis'),
            'hasil' => $redistribusi->saran($instansiId, array_intersect_key($filters, ['jenis' => 1])),
        ]);
    }

    private function context(): array
    {
        $instansiId = $this->selectedInstansiId() ?? $this->instansiOptions()->first()?->id;
        abort_unless($instansiId, 404, 'Belum ada instansi.');
        $this->authorizeInstansi($instansiId);

        return [$instansiId, array_filter([
            'unit_ids' => request('unit_kerja_id') ? UnitKerja::descendantIds(request()->integer('unit_kerja_id')) : null,
            'jenis' => request('jenis'),
        ])];
    }
}
