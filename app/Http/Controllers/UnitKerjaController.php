<?php

namespace App\Http\Controllers;

use App\Models\UnitKerja;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    private function formProps(?UnitKerja $unit, ?int $instansiId): array
    {
        return [
            'data' => $unit,
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
            'nama' => 'required|string|max:255',
            'eselon' => ['nullable', Rule::in(Referensi::ESELON)],
            'urutan' => 'nullable|integer|min:0',
        ]);

        $this->authorizeInstansi((int) $data['instansi_id']);
        $data['urutan'] ??= 0;

        return $data;
    }
}
