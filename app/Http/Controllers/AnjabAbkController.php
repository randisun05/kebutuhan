<?php

namespace App\Http\Controllers;

use App\Models\AnjabAbk;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitKerja;
use App\Support\Referensi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnjabAbkController extends Controller
{
    public function index()
    {
        $instansiId = $this->selectedInstansiId();

        $datas = $this->scoped(AnjabAbk::query())
            ->with(['instansi:id,nama', 'unitKerja:id,nama', 'jabatan:id,nama,jenis'])
            ->when($instansiId, fn ($q) => $q->where('instansi_id', $instansiId))
            ->when(request('unit_kerja_id'), fn ($q, $u) => $q->whereIn('unit_kerja_id', UnitKerja::descendantIds((int) $u)))
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(request('q'), fn ($q, $s) => $q->whereHas('jabatan', fn ($w) => $w->where('nama', 'like', "%{$s}%")))
            ->latest('updated_at')
            ->paginate(15)->withQueryString();

        // existing per posisi pada halaman ini, untuk nilai Efektivitas Jabatan
        $existing = $this->existingFor($datas->getCollection());
        $datas->getCollection()->transform(fn ($a) => $this->withEfektivitas($a, $existing[$a->unit_kerja_id.'-'.$a->jabatan_id] ?? 0));

        return inertia('Anjab/Index', [
            'datas' => $datas,
            'filters' => request()->only('unit_kerja_id', 'status', 'q') + ['instansi_id' => $instansiId],
            'instansiOptions' => $this->instansiOptions(),
            'unitOptions' => $instansiId ? UnitKerja::flatTree($instansiId) : [],
            'canEdit' => $this->canEdit(),
        ]);
    }

    public function create()
    {
        abort_unless($this->canEdit(), 403);

        return inertia('Anjab/Form', $this->formProps(null, $this->selectedInstansiId()));
    }

    public function store(Request $request)
    {
        abort_unless($this->canEdit(), 403);
        $anjab = $this->save($request, new AnjabAbk(['created_by' => $request->user()->id]));

        return redirect()->route('anjab.show', $anjab)->with('success', 'Anjab/ABK berhasil disimpan.');
    }

    public function show(AnjabAbk $anjab)
    {
        $this->authorizeInstansi($anjab->instansi_id);
        $anjab->load(['instansi:id,nama', 'unitKerja:id,nama', 'jabatan', 'uraianTugas']);

        $existing = Pegawai::where('unit_kerja_id', $anjab->unit_kerja_id)
            ->where('jabatan_id', $anjab->jabatan_id)->where('is_active', true)->count();

        return inertia('Anjab/Show', [
            'data' => $this->withEfektivitas($anjab, $existing),
            'canEdit' => $this->canEdit(),
        ]);
    }

    public function edit(AnjabAbk $anjab)
    {
        abort_unless($this->canEdit(), 403);
        $this->authorizeInstansi($anjab->instansi_id);

        return inertia('Anjab/Form', $this->formProps($anjab->load('uraianTugas'), $anjab->instansi_id));
    }

    public function update(Request $request, AnjabAbk $anjab)
    {
        abort_unless($this->canEdit(), 403);
        $this->authorizeInstansi($anjab->instansi_id);
        $this->save($request, $anjab);

        return redirect()->route('anjab.show', $anjab)->with('success', 'Anjab/ABK berhasil diperbarui.');
    }

    public function destroy(AnjabAbk $anjab)
    {
        abort_unless($this->canEdit(), 403);
        $this->authorizeInstansi($anjab->instansi_id);
        $anjab->delete();

        return redirect()->route('anjab.index')->with('success', 'Anjab/ABK berhasil dihapus.');
    }

    /** Final = hasil ABK dipakai sebagai angka kebutuhan pada monitoring & usulan. */
    public function toggleStatus(AnjabAbk $anjab)
    {
        abort_unless($this->canEdit(), 403);
        $this->authorizeInstansi($anjab->instansi_id);

        $anjab->update(['status' => $anjab->status === 'final' ? 'draft' : 'final']);

        return back()->with('success', $anjab->status === 'final'
            ? 'ABK difinalkan dan masuk perhitungan monitoring.'
            : 'ABK dikembalikan ke draft dan dikeluarkan dari perhitungan monitoring.');
    }

    private function save(Request $request, AnjabAbk $anjab): AnjabAbk
    {
        $data = $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'unit_kerja_id' => ['required', Rule::exists('unit_kerjas', 'id')->where('instansi_id', $request->integer('instansi_id'))],
            'jabatan_id' => ['required', 'exists:jabatans,id',
                Rule::unique('anjab_abks')->where('unit_kerja_id', $request->integer('unit_kerja_id'))->ignore($anjab->id)],
            'tahun' => 'required|integer|min:2000|max:2100',
            'ikhtisar_jabatan' => 'nullable|string',
            'waktu_kerja_efektif' => 'required|integer|min:1',
            'status' => 'required|in:draft,final',
            'uraian' => 'required|array|min:1',
            'uraian.*.uraian_tugas' => 'required|string',
            'uraian.*.hasil_kerja' => 'nullable|string|max:255',
            'uraian.*.volume' => 'required|integer|min:0',
            'uraian.*.norma_waktu' => 'required|integer|min:0',
        ], [
            'jabatan_id.unique' => 'Jabatan ini sudah memiliki Anjab/ABK pada unit kerja tersebut.',
            'uraian.required' => 'Minimal satu uraian tugas harus diisi.',
        ]);

        $this->authorizeInstansi((int) $data['instansi_id']);

        return DB::transaction(function () use ($anjab, $data) {
            $anjab->fill(collect($data)->except('uraian')->all())->save();

            $anjab->uraianTugas()->delete();
            foreach (array_values($data['uraian']) as $i => $row) {
                $anjab->uraianTugas()->create($row + ['urutan' => $i + 1]);
            }

            $anjab->recalculate();

            return $anjab;
        });
    }

    private function formProps(?AnjabAbk $anjab, ?int $instansiId): array
    {
        return [
            'data' => $anjab,
            'instansiOptions' => $this->instansiOptions(),
            'unitOptions' => $instansiId ? UnitKerja::flatTree($instansiId) : [],
            'jabatanOptions' => Jabatan::where('is_active', true)->orderBy('jenis')->orderBy('nama')->get(['id', 'nama', 'jenis', 'kode']),
            'instansiId' => $instansiId,
            'wkeDefault' => Referensi::WAKTU_KERJA_EFEKTIF,
        ];
    }

    private function canEdit(): bool
    {
        return in_array(request()->user()->role?->value, ['admin', 'operator_instansi'], true);
    }

    private function existingFor($anjabs): array
    {
        if ($anjabs->isEmpty()) {
            return [];
        }

        return Pegawai::where('is_active', true)
            ->whereIn('unit_kerja_id', $anjabs->pluck('unit_kerja_id')->unique())
            ->whereIn('jabatan_id', $anjabs->pluck('jabatan_id')->unique())
            ->selectRaw('unit_kerja_id, jabatan_id, COUNT(*) as jumlah')
            ->groupBy('unit_kerja_id', 'jabatan_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => (int) $r->jumlah])
            ->all();
    }

    /**
     * Efektivitas Jabatan (EJ) = beban kerja / (pegawai existing × waktu kerja efektif),
     * kemudian dikategorikan menjadi Prestasi Efektivitas Jabatan (PEJ) A–E.
     */
    private function withEfektivitas(AnjabAbk $anjab, int $existing): AnjabAbk
    {
        $ej = $existing > 0 ? round($anjab->total_beban_kerja / ($existing * $anjab->waktu_kerja_efektif), 2) : null;

        return $anjab->setAttribute('existing', $existing)
            ->setAttribute('selisih', $existing - $anjab->kebutuhan)
            ->setAttribute('ej', $ej)
            ->setAttribute('pej', Referensi::pej($ej));
    }
}
