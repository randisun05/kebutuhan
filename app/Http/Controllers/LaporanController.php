<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\UnitKerja;
use App\Services\LaporanService;
use App\Support\Referensi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function __construct(private LaporanService $laporan) {}

    public function index()
    {
        $params = $this->params();

        return inertia('Laporan/Index', [
            'jenisOptions' => LaporanService::JENIS,
            'filters' => $params,
            'instansiOptions' => $this->instansiOptions(),
            'unitOptions' => ! empty($params['instansi_id']) ? UnitKerja::flatTree($params['instansi_id']) : [],
            'jenisJabatanOptions' => Referensi::options(Referensi::JENIS_JABATAN),
            'hasil' => fn () => $params['jenis'] ? $this->limit($this->laporan->buat($params['jenis'], $params)) : null,
        ]);
    }

    public function unduh(string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $params = $this->params();
        abort_unless($params['jenis'], 422, 'Pilih jenis laporan.');

        $hasil = $this->laporan->buat($params['jenis'], $params);
        $nama = 'laporan-'.str_replace('_', '-', $params['jenis']).'-'.now()->format('Ymd-His');
        $baris = $hasil['total'] ? [...$hasil['baris'], $hasil['total']] : $hasil['baris'];

        if ($format === 'xlsx') {
            return Excel::download(new ArrayExport($hasil['kolom'], $baris), $nama.'.xlsx');
        }

        return Pdf::loadView('pdf.laporan', $hasil + ['oleh' => request()->user()->name])
            ->setPaper('a4', count($hasil['kolom']) > 7 ? 'landscape' : 'portrait')
            ->download($nama.'.pdf');
    }

    private function params(): array
    {
        $data = request()->validate([
            'jenis' => ['nullable', Rule::in(array_keys(LaporanService::JENIS))],
            'unit_kerja_id' => 'nullable|integer',
            'jenis_jabatan' => ['nullable', Rule::in(array_keys(Referensi::JENIS_JABATAN))],
            'tahun' => 'nullable|integer|min:1|max:2100',
            'bulan' => 'nullable|integer|in:1,3,6,12,24',
        ]);

        $instansiId = $this->selectedInstansiId();
        if ($instansiId) {
            $this->authorizeInstansi($instansiId);
        }

        return ['jenis' => $data['jenis'] ?? null, 'instansi_id' => $instansiId] + array_filter($data, fn ($v, $k) => $k !== 'jenis' && $v !== null, ARRAY_FILTER_USE_BOTH);
    }

    /** Pratinjau di layar dibatasi 300 baris; unduhan memuat seluruh baris. */
    private function limit(array $hasil): array
    {
        $hasil['jumlah_baris'] = count($hasil['baris']);
        $hasil['baris'] = array_slice($hasil['baris'], 0, 300);

        return $hasil;
    }
}
