<?php

namespace App\Http\Controllers;

use App\Exports\PegawaiTemplateExport;
use App\Imports\PegawaiImport;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitKerja;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class PegawaiController extends Controller
{
    public function index()
    {
        $instansiId = $this->selectedInstansiId();

        $datas = $this->scoped(Pegawai::query())
            ->with(['instansi:id,nama', 'unitKerja:id,nama', 'jabatan:id,nama,jenis'])
            ->when($instansiId, fn ($q) => $q->where('instansi_id', $instansiId))
            ->when(request('unit_kerja_id'), fn ($q, $u) => $q->whereIn('unit_kerja_id', UnitKerja::descendantIds((int) $u)))
            ->when(request('jenis'), fn ($q, $j) => $q->whereHas('jabatan', fn ($w) => $w->where('jenis', $j)))
            ->when(request('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('nama', 'like', "%{$s}%")->orWhere('nip', 'like', "%{$s}%")))
            ->when(request('aktif', '1') !== 'all', fn ($q) => $q->where('is_active', request('aktif', '1') === '1'))
            ->when(request()->boolean('pensiun'), fn ($q) => $q->whereNotNull('tmt_pensiun')
                ->where('tmt_pensiun', '<=', now()->addYears(Referensi::HORIZON_PROYEKSI_TAHUN)->toDateString()))
            ->orderBy('nama')
            ->paginate(20)->withQueryString();

        return inertia('Pegawai/Index', [
            'datas' => $datas,
            'filters' => request()->only('q', 'unit_kerja_id', 'jenis', 'aktif', 'pensiun') + ['instansi_id' => $instansiId],
            'instansiOptions' => $this->instansiOptions(),
            'unitOptions' => $instansiId ? UnitKerja::flatTree($instansiId) : [],
            'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN),
        ]);
    }

    public function create()
    {
        return inertia('Pegawai/Form', $this->formProps(null, $this->selectedInstansiId()));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Pegawai::create($data);

        return redirect()->route('pegawai.index', ['instansi_id' => $data['instansi_id']])->with('success', 'Pegawai berhasil ditambahkan.');
    }

    public function edit(Pegawai $pegawai)
    {
        $this->authorizeInstansi($pegawai->instansi_id);

        return inertia('Pegawai/Form', $this->formProps($pegawai, $pegawai->instansi_id));
    }

    public function update(Request $request, Pegawai $pegawai)
    {
        $this->authorizeInstansi($pegawai->instansi_id);
        $pegawai->update($this->validated($request, $pegawai));

        return redirect()->route('pegawai.index', ['instansi_id' => $pegawai->instansi_id])->with('success', 'Data pegawai berhasil diperbarui.');
    }

    public function destroy(Pegawai $pegawai)
    {
        $this->authorizeInstansi($pegawai->instansi_id);
        $pegawai->delete();

        return back()->with('success', 'Data pegawai berhasil dihapus.');
    }

    public function importForm()
    {
        return inertia('Pegawai/Import', ['instansiOptions' => $this->instansiOptions()]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);
        $this->authorizeInstansi($request->integer('instansi_id'));

        $import = new PegawaiImport($request->integer('instansi_id'));
        Excel::import($import, $request->file('file'));

        $message = "Impor selesai: {$import->created} ditambahkan, {$import->updated} diperbarui.";
        if ($import->errors) {
            return redirect()->route('pegawai.import.form')
                ->with('warning', $message.' '.count($import->errors).' baris gagal.')
                ->with('importErrors', array_slice($import->errors, 0, 100));
        }

        return redirect()->route('pegawai.index', ['instansi_id' => $request->integer('instansi_id')])->with('success', $message);
    }

    public function template()
    {
        return Excel::download(new PegawaiTemplateExport, 'template-impor-pegawai.xlsx');
    }

    private function formProps(?Pegawai $pegawai, ?int $instansiId): array
    {
        return [
            'data' => $pegawai,
            'instansiOptions' => $this->instansiOptions(),
            'unitOptions' => $instansiId ? UnitKerja::flatTree($instansiId) : [],
            'jabatanOptions' => Jabatan::where('is_active', true)->orderBy('jenis')->orderBy('nama')->get(['id', 'nama', 'jenis']),
            'instansiId' => $instansiId,
        ];
    }

    private function validated(Request $request, ?Pegawai $pegawai = null): array
    {
        $data = $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'unit_kerja_id' => ['required', Rule::exists('unit_kerjas', 'id')->where('instansi_id', $request->integer('instansi_id'))],
            'jabatan_id' => 'required|exists:jabatans,id',
            'nip' => ['required', 'digits:18', Rule::unique('pegawais')->ignore($pegawai)],
            'nama' => 'required|string|max:255',
            'status_kepegawaian' => 'required|in:pns,pppk',
            'golongan' => 'nullable|string|max:10',
            'pendidikan' => 'nullable|string|max:50',
            'tanggal_lahir' => 'nullable|date|before:today',
            'tmt_jabatan' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        $this->authorizeInstansi((int) $data['instansi_id']);

        return $data;
    }
}
