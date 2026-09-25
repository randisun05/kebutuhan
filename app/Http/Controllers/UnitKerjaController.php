<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Imports\UnitKerjaImport;
use App\Models\Instansi;
use App\Models\UnitKerja;
use App\Services\MonitoringService;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class UnitKerjaController extends Controller
{
    public function index()
    {
        $instansiOptions = $this->instansiOptions();
        $instansiId = $this->selectedInstansiId() ?? $instansiOptions->first()?->id;

        $counts = UnitKerja::where('instansi_id', $instansiId)
            ->withCount(['pegawais' => fn ($q) => $q->where('is_active', true)])
            ->pluck('pegawais_count', 'id');

        $tree = $instansiId ? UnitKerja::flatTree($instansiId)->map(fn ($u) => $u + ['pegawai' => $counts[$u['id']] ?? 0]) : collect();

        if ($s = request('q')) {
            $tree = $tree->filter(fn ($u) => str_contains(mb_strtolower($u['nama']), mb_strtolower($s)))->values();
        }

        return inertia('UnitKerja/Index', [
            'units' => $tree,
            'instansiOptions' => $instansiOptions,
            'filters' => ['instansi_id' => $instansiId, 'q' => request('q')],
        ]);
    }

    public function create()
    {
        $instansiId = $this->selectedInstansiId() ?? request()->integer('instansi_id');

        return inertia('UnitKerja/Form', $this->formProps(null, $instansiId));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        UnitKerja::create($data);

        return redirect()->route('unit-kerja.index', ['instansi_id' => $data['instansi_id']])->with('success', 'Unit kerja berhasil ditambahkan.');
    }

    public function edit(UnitKerja $unitKerja)
    {
        $this->authorizeInstansi($unitKerja->instansi_id);

        return inertia('UnitKerja/Form', $this->formProps($unitKerja, $unitKerja->instansi_id));
    }

    public function update(Request $request, UnitKerja $unitKerja)
    {
        $this->authorizeInstansi($unitKerja->instansi_id);
        $data = $this->validated($request, $unitKerja);

        if ($data['parent_id'] && in_array((int) $data['parent_id'], UnitKerja::descendantIds($unitKerja->id), true)) {
            return back()->withErrors(['parent_id' => 'Unit induk tidak boleh unit itu sendiri atau sub-unitnya.']);
        }

        $unitKerja->update($data);

        return redirect()->route('unit-kerja.index', ['instansi_id' => $unitKerja->instansi_id])->with('success', 'Unit kerja berhasil diperbarui.');
    }

    public function destroy(UnitKerja $unitKerja)
    {
        $this->authorizeInstansi($unitKerja->instansi_id);

        if ($unitKerja->pegawais()->exists() || $unitKerja->children()->exists()) {
            return back()->with('error', 'Unit kerja masih memiliki pegawai atau sub-unit.');
        }

        $unitKerja->delete();

        return back()->with('success', 'Unit kerja berhasil dihapus.');
    }

    public function toggle(UnitKerja $unitKerja)
    {
        $this->authorizeInstansi($unitKerja->instansi_id);
        $unitKerja->update(['is_active' => ! $unitKerja->is_active]);

        return back()->with('success', 'Unit kerja '.($unitKerja->is_active ? 'diaktifkan' : 'dinonaktifkan').'.');
    }

    /** Impor struktur organisasi dari Excel: kode, nama, kode_induk, eselon, nama_jabatan_pimpinan, siasn_unor_id. */
    public function import(Request $request)
    {
        $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);
        $this->authorizeInstansi($request->integer('instansi_id'));

        $import = new UnitKerjaImport($request->integer('instansi_id'));
        Excel::import($import, $request->file('file'));

        $message = "Impor unit kerja: {$import->created} ditambahkan, {$import->updated} diperbarui.";

        return redirect()->route('unit-kerja.index', ['instansi_id' => $request->integer('instansi_id')])
            ->with($import->errors ? 'warning' : 'success', $message.($import->errors ? ' '.count($import->errors).' baris gagal.' : ''))
            ->with('importErrors', array_slice($import->errors, 0, 100));
    }

    public function template()
    {
        return Excel::download(new ArrayExport(
            ['kode', 'nama', 'kode_induk', 'eselon', 'nama_jabatan_pimpinan', 'siasn_unor_id'],
            [['UK-001', 'Sekretariat Daerah', '', 'II', 'Sekretaris Daerah', ''], ['UK-010', 'Bagian Organisasi', 'UK-001', 'III', 'Kepala Bagian Organisasi', '']]
        ), 'template-impor-unit-kerja.xlsx');
    }

    /**
     * Peta jabatan: hierarki unit beserta jabatan, kelas, bezetting (B), kebutuhan (K) dan selisih.
     */
    public function petaJabatan(MonitoringService $monitoring)
    {
        $instansiOptions = $this->instansiOptions();
        $instansiId = $this->selectedInstansiId() ?? $instansiOptions->first()?->id;
        abort_unless($instansiId, 404);
        $this->authorizeInstansi($instansiId);

        $positions = $monitoring->positionsQuery(['instansi_id' => $instansiId])
            ->join('jabatans as jb', 'jb.id', '=', 'p.jabatan_id')
            ->select('p.*', 'jb.kelas_jabatan')
            ->orderByRaw("CASE p.jenis WHEN 'jpt_utama' THEN 1 WHEN 'jpt_madya' THEN 2 WHEN 'jpt_pratama' THEN 3 WHEN 'administrator' THEN 4 WHEN 'pengawas' THEN 5 WHEN 'fungsional' THEN 6 ELSE 7 END")
            ->orderBy('p.jabatan_nama')
            ->get()
            ->map(fn ($p) => $monitoring->decoratePosition((array) $p))
            ->groupBy('unit_kerja_id');

        return inertia('UnitKerja/PetaJabatan', [
            'instansi' => Instansi::find($instansiId, ['id', 'nama', 'kode']),
            'instansiOptions' => $instansiOptions,
            'units' => UnitKerja::flatTree($instansiId)->map(fn ($u) => $u + ['jabatan' => $positions->get($u['id'], collect())->values()]),
        ]);
    }

    private function formProps(?UnitKerja $unit, ?int $instansiId): array
    {
        return [
            'data' => $unit,
            'pegawai' => $unit ? $unit->pegawais()->where('is_active', true)->count() : 0,
            'instansiOptions' => $this->instansiOptions(),
            'parentOptions' => $instansiId ? UnitKerja::flatTree($instansiId) : [],
            'eselonOptions' => Referensi::ESELON,
            'instansiId' => $instansiId,
        ];
    }

    private function validated(Request $request, ?UnitKerja $unit = null): array
    {
        $data = $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'parent_id' => ['nullable', Rule::exists('unit_kerjas', 'id')->where('instansi_id', $request->integer('instansi_id'))],
            'kode' => 'nullable|string|max:30',
            'siasn_unor_id' => 'nullable|string|max:64',
            'nama' => 'required|string|max:255',
            'eselon' => ['nullable', Rule::in(Referensi::ESELON)],
            'nama_jabatan_pimpinan' => 'nullable|string|max:255',
            'urutan' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $this->authorizeInstansi((int) $data['instansi_id']);
        $data['urutan'] ??= 0;

        return $data;
    }
}
