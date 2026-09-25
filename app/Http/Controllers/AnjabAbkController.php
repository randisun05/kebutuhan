<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Imports\AbkImport;
use App\Models\AnjabAbk;
use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitKerja;
use App\Support\Referensi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class AnjabAbkController extends Controller
{
    public function index()
    {
        $instansiId = $this->selectedInstansiId();

        $datas = $this->filteredQuery($instansiId)
            ->with(['instansi:id,nama', 'unitKerja:id,nama', 'jabatan:id,nama,jenis'])
            ->latest('updated_at')
            ->paginate(15)->withQueryString();

        // existing per posisi pada halaman ini, untuk nilai Efektivitas Jabatan
        $existing = $this->existingFor($datas->getCollection());
        $datas->getCollection()->transform(fn ($a) => $this->withEfektivitas($a, $existing[$a->unit_kerja_id.'-'.$a->jabatan_id] ?? 0));

        $tahunOptions = $this->scoped(AnjabAbk::query())->distinct()->orderByDesc('tahun')->pluck('tahun');

        return inertia('Anjab/Index', [
            'datas' => $datas,
            'filters' => request()->only('unit_kerja_id', 'status', 'q', 'tahun') + ['instansi_id' => $instansiId],
            'instansiOptions' => $this->instansiOptions(),
            'unitOptions' => $instansiId ? UnitKerja::flatTree($instansiId) : [],
            'tahunOptions' => $tahunOptions,
            'canEdit' => $this->canEdit(),
            'ringkasan' => fn () => [
                'total' => (clone $this->filteredQuery($instansiId))->count(),
                'final' => (clone $this->filteredQuery($instansiId))->where('status', 'final')->count(),
                'kebutuhan' => (int) (clone $this->filteredQuery($instansiId))->where('status', 'final')->sum('kebutuhan'),
            ],
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
        $anjab->load(['instansi:id,nama', 'unitKerja:id,nama,nama_jabatan_pimpinan', 'jabatan', 'uraianTugas', 'finalizer:id,name']);

        $existing = $this->existingCount($anjab);
        $tahun = range((int) now()->format('Y') + 1, (int) now()->format('Y') + Referensi::HORIZON_PROYEKSI_TAHUN);

        return inertia('Anjab/Show', [
            'data' => $this->withEfektivitas($anjab, $existing),
            'canEdit' => $this->canEdit(),
            'informasiFields' => Referensi::INFORMASI_JABATAN,
            'periodeLabel' => Referensi::PERIODE_LABEL,
            'periodePerTahun' => Referensi::PERIODE_PER_TAHUN,
            'proyeksi' => collect($tahun)->map(fn ($t) => ['tahun' => $t, 'kebutuhan' => $anjab->kebutuhanPadaTahun($t)]),
            'versi' => AnjabAbk::where('unit_kerja_id', $anjab->unit_kerja_id)->where('jabatan_id', $anjab->jabatan_id)
                ->orderByDesc('tahun')->get(['id', 'tahun', 'status', 'kebutuhan']),
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

    /** Final = hasil ABK dipakai sebagai angka kebutuhan pada monitoring, proyeksi & usulan. */
    public function toggleStatus(AnjabAbk $anjab)
    {
        abort_unless($this->canEdit(), 403);
        $this->authorizeInstansi($anjab->instansi_id);

        $final = $anjab->status !== 'final';
        if ($final && $anjab->uraianTugas()->count() === 0) {
            return back()->with('error', 'ABK tanpa uraian tugas tidak dapat difinalkan.');
        }

        $anjab->update([
            'status' => $final ? 'final' : 'draft',
            'finalized_by' => $final ? request()->user()->id : null,
            'finalized_at' => $final ? now() : null,
        ]);

        return back()->with('success', $final
            ? 'ABK difinalkan dan dipakai dalam perhitungan kebutuhan.'
            : 'ABK dikembalikan ke draft dan dikeluarkan dari perhitungan.');
    }

    /** Salin ABK ke tahun lain (misal penyusunan ABK tahun berikutnya) sebagai draft. */
    public function duplicate(Request $request, AnjabAbk $anjab)
    {
        abort_unless($this->canEdit(), 403);
        $this->authorizeInstansi($anjab->instansi_id);

        $data = $request->validate([
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100',
                Rule::unique('anjab_abks')->where('unit_kerja_id', $anjab->unit_kerja_id)->where('jabatan_id', $anjab->jabatan_id)],
        ], ['tahun.unique' => 'ABK untuk tahun tersebut sudah ada.']);

        $baru = DB::transaction(function () use ($anjab, $data, $request) {
            $baru = $anjab->replicate(['status', 'finalized_by', 'finalized_at', 'created_by']);
            $baru->fill(['tahun' => $data['tahun'], 'status' => 'draft', 'created_by' => $request->user()->id])->save();
            foreach ($anjab->uraianTugas as $t) {
                $baru->uraianTugas()->create($t->only(['uraian_tugas', 'hasil_kerja', 'volume', 'satuan_periode', 'norma_waktu', 'urutan']));
            }
            $baru->recalculate();

            return $baru;
        });

        return redirect()->route('anjab.edit', $baru)->with('success', "ABK disalin ke tahun {$data['tahun']} sebagai draft. Sesuaikan volume beban kerjanya.");
    }

    /** Salin semua ABK final satu instansi ke tahun baru sekaligus. */
    public function duplicateBulk(Request $request)
    {
        abort_unless($this->canEdit(), 403);
        $data = $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'dari_tahun' => 'required|integer',
            'ke_tahun' => 'required|integer|different:dari_tahun|min:2000|max:2100',
        ]);
        $this->authorizeInstansi((int) $data['instansi_id']);

        $count = 0;
        DB::transaction(function () use ($data, $request, &$count) {
            $sumber = AnjabAbk::with('uraianTugas')->where('instansi_id', $data['instansi_id'])
                ->where('tahun', $data['dari_tahun'])->where('status', 'final')->get();

            foreach ($sumber as $anjab) {
                $ada = AnjabAbk::where('unit_kerja_id', $anjab->unit_kerja_id)->where('jabatan_id', $anjab->jabatan_id)
                    ->where('tahun', $data['ke_tahun'])->exists();
                if ($ada) {
                    continue;
                }
                $baru = $anjab->replicate(['status', 'finalized_by', 'finalized_at', 'created_by']);
                $baru->fill(['tahun' => $data['ke_tahun'], 'status' => 'draft', 'created_by' => $request->user()->id])->save();
                foreach ($anjab->uraianTugas as $t) {
                    $baru->uraianTugas()->create($t->only(['uraian_tugas', 'hasil_kerja', 'volume', 'satuan_periode', 'norma_waktu', 'urutan']));
                }
                $baru->recalculate();
                $count++;
            }
        });

        return back()->with($count ? 'success' : 'info', $count
            ? "{$count} ABK final tahun {$data['dari_tahun']} disalin ke {$data['ke_tahun']} sebagai draft."
            : 'Tidak ada ABK final yang dapat disalin.');
    }

    public function import(Request $request)
    {
        abort_unless($this->canEdit(), 403);
        $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);
        $this->authorizeInstansi($request->integer('instansi_id'));

        $import = new AbkImport($request->integer('instansi_id'), $request->user()->id);
        Excel::import($import, $request->file('file'));

        $message = "Impor ABK: {$import->created} ABK baru, {$import->updated} diperbarui, {$import->rows} uraian tugas.";

        return redirect()->route('anjab.index', ['instansi_id' => $request->integer('instansi_id')])
            ->with($import->errors ? 'warning' : 'success', $message.($import->errors ? ' '.count($import->errors).' baris gagal.' : ''))
            ->with('importErrors', array_slice($import->errors, 0, 100));
    }

    public function template()
    {
        return Excel::download(new ArrayExport(
            ['kode_unit', 'kode_jabatan', 'tahun', 'uraian_tugas', 'hasil_kerja', 'volume', 'satuan_periode', 'norma_waktu'],
            [
                ['UK-010', 'JF-ASDM-1', now()->format('Y'), 'Menyusun rencana kebutuhan pegawai', 'Dokumen', 12, 'tahun', 900],
                ['UK-010', 'JF-ASDM-1', now()->format('Y'), 'Memverifikasi usul kenaikan pangkat', 'Berkas', 20, 'bulan', 45],
            ]
        ), 'template-impor-abk.xlsx');
    }

    public function export()
    {
        $instansiId = $this->selectedInstansiId();
        $rows = $this->filteredQuery($instansiId)
            ->with(['instansi:id,nama', 'unitKerja:id,nama', 'jabatan:id,nama,jenis'])
            ->orderBy('instansi_id')->orderBy('unit_kerja_id')->get();
        $existing = $this->existingFor($rows);

        $data = $rows->values()->map(function ($a, $i) use ($existing) {
            $a = $this->withEfektivitas($a, $existing[$a->unit_kerja_id.'-'.$a->jabatan_id] ?? 0);

            return [$i + 1, $a->instansi?->nama, $a->unitKerja?->nama, $a->jabatan?->nama, $a->jabatan?->jenis_label, $a->tahun,
                round($a->total_beban_kerja / 60, 2), $a->waktu_kerja_efektif / 60, $a->kebutuhan_hitung, $a->kebutuhan,
                $a->existing, $a->selisih, $a->ej, $a->pej['nilai'] ?? null, $a->status];
        })->all();

        return Excel::download(new ArrayExport(
            ['No', 'Instansi', 'Unit Kerja', 'Jabatan', 'Jenis', 'Tahun', 'Beban Kerja (jam)', 'WKE (jam)', 'Hasil Hitung', 'Kebutuhan',
                'Existing', 'Selisih', 'EJ', 'PEJ', 'Status'],
            $data
        ), 'rekap-abk-'.now()->format('Ymd-His').'.xlsx');
    }

    /** Dokumen Anjab & ABK (informasi jabatan) dalam PDF. */
    public function pdf(AnjabAbk $anjab)
    {
        $this->authorizeInstansi($anjab->instansi_id);
        $anjab->load(['instansi', 'unitKerja.parent', 'jabatan', 'uraianTugas']);

        return Pdf::loadView('pdf.anjab', [
            'anjab' => $this->withEfektivitas($anjab, $this->existingCount($anjab)),
            'fields' => Referensi::INFORMASI_JABATAN,
            'periode' => Referensi::PERIODE_LABEL,
        ])->setPaper('a4')->download('anjab-'.str($anjab->jabatan->nama)->slug().'-'.$anjab->tahun.'.pdf');
    }

    /**
     * Rekap efektivitas unit (EU) = Σ beban kerja jabatan ÷ (Σ pegawai × waktu kerja efektif),
     * dengan kategori PEU A–E, berdasarkan ABK final terbaru per jabatan.
     */
    public function rekapUnit()
    {
        $instansiOptions = $this->instansiOptions();
        $instansiId = $this->selectedInstansiId() ?? $instansiOptions->first()?->id;
        abort_unless($instansiId, 404);
        $this->authorizeInstansi($instansiId);

        $abk = DB::table('anjab_abks as a')
            ->where('a.instansi_id', $instansiId)->where('a.status', 'final')
            ->whereRaw('a.tahun = (SELECT MAX(b.tahun) FROM anjab_abks b WHERE b.unit_kerja_id = a.unit_kerja_id AND b.jabatan_id = a.jabatan_id AND b.status = ?)', ['final'])
            ->groupBy('a.unit_kerja_id')
            ->selectRaw('a.unit_kerja_id, COUNT(*) as jabatan, SUM(a.total_beban_kerja / a.waktu_kerja_efektif) as beban_pegawai, SUM(a.kebutuhan) as kebutuhan')
            ->get()->keyBy('unit_kerja_id');

        $pegawai = Pegawai::where('instansi_id', $instansiId)->where('is_active', true)
            ->selectRaw('unit_kerja_id, COUNT(*) as jumlah')->groupBy('unit_kerja_id')->pluck('jumlah', 'unit_kerja_id');

        $units = UnitKerja::flatTree($instansiId)->map(function ($u) use ($abk, $pegawai) {
            $row = $abk->get($u['id']);
            $existing = (int) ($pegawai[$u['id']] ?? 0);
            $beban = $row ? (float) $row->beban_pegawai : 0.0;
            $eu = $existing > 0 && $row ? round($beban / $existing, 2) : null;

            return $u + [
                'jabatan' => (int) ($row->jabatan ?? 0),
                'beban_pegawai' => round($beban, 2),
                'kebutuhan' => (int) ($row->kebutuhan ?? 0),
                'existing' => $existing,
                'eu' => $eu,
                'peu' => Referensi::pej($eu),
            ];
        });

        $totBeban = $units->sum('beban_pegawai');
        $totExisting = $units->sum('existing');
        $euInstansi = $totExisting > 0 ? round($totBeban / $totExisting, 2) : null;

        return inertia('Anjab/RekapUnit', [
            'instansi' => Instansi::find($instansiId, ['id', 'nama']),
            'instansiOptions' => $instansiOptions,
            'units' => $units,
            'total' => [
                'beban_pegawai' => round($totBeban, 2), 'kebutuhan' => $units->sum('kebutuhan'), 'existing' => $totExisting,
                'eu' => $euInstansi, 'peu' => Referensi::pej($euInstansi),
            ],
        ]);
    }

    private function filteredQuery(?int $instansiId)
    {
        return $this->scoped(AnjabAbk::query())
            ->when($instansiId, fn ($q) => $q->where('instansi_id', $instansiId))
            ->when(request('unit_kerja_id'), fn ($q, $u) => $q->whereIn('unit_kerja_id', UnitKerja::descendantIds((int) $u)))
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->when(request('tahun'), fn ($q, $t) => $q->where('tahun', $t))
            ->when(request('q'), fn ($q, $s) => $q->whereHas('jabatan', fn ($w) => $w->where('nama', 'like', "%{$s}%")));
    }

    private function save(Request $request, AnjabAbk $anjab): AnjabAbk
    {
        $infoRules = [];
        foreach (Referensi::INFORMASI_JABATAN as $key => $meta) {
            $infoRules["informasi.{$key}"] = $meta['tipe'] === 'list' ? 'nullable|array' : 'nullable|string|max:5000';
            if ($meta['tipe'] === 'list') {
                $infoRules["informasi.{$key}.*"] = 'nullable|string|max:1000';
            }
        }

        $data = $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'unit_kerja_id' => ['required', Rule::exists('unit_kerjas', 'id')->where('instansi_id', $request->integer('instansi_id'))],
            'jabatan_id' => ['required', 'exists:jabatans,id',
                Rule::unique('anjab_abks')->where('unit_kerja_id', $request->integer('unit_kerja_id'))
                    ->where('tahun', $request->integer('tahun'))->ignore($anjab->id)],
            'tahun' => 'required|integer|min:2000|max:2100',
            'ikhtisar_jabatan' => 'nullable|string',
            'kelas_jabatan' => 'nullable|integer|min:1|max:17',
            'waktu_kerja_efektif' => 'required|integer|min:1',
            'pertumbuhan_beban' => 'nullable|numeric|min:-50|max:100',
            'status' => 'required|in:draft,final',
            'informasi' => 'nullable|array',
            'uraian' => 'required|array|min:1',
            'uraian.*.uraian_tugas' => 'required|string',
            'uraian.*.hasil_kerja' => 'nullable|string|max:255',
            'uraian.*.volume' => 'required|integer|min:0',
            'uraian.*.satuan_periode' => ['nullable', Rule::in(array_keys(Referensi::PERIODE_PER_TAHUN))],
            'uraian.*.norma_waktu' => 'required|integer|min:0',
        ] + $infoRules, [
            'jabatan_id.unique' => 'Jabatan ini sudah memiliki Anjab/ABK pada unit dan tahun tersebut.',
            'uraian.required' => 'Minimal satu uraian tugas harus diisi.',
        ]);

        $this->authorizeInstansi((int) $data['instansi_id']);

        // buang baris kosong pada daftar informasi jabatan
        $data['informasi'] = collect($data['informasi'] ?? [])
            ->only(array_keys(Referensi::INFORMASI_JABATAN))
            ->map(fn ($v) => is_array($v) ? array_values(array_filter(array_map('trim', $v), fn ($x) => $x !== '')) : trim((string) $v))
            ->all();
        $data['pertumbuhan_beban'] ??= 0;

        return DB::transaction(function () use ($anjab, $data, $request) {
            $wasFinal = $anjab->status === 'final';
            $anjab->fill(collect($data)->except('uraian')->all());
            if ($anjab->status === 'final' && ! $wasFinal) {
                $anjab->finalized_by = $request->user()->id;
                $anjab->finalized_at = now();
            }
            $anjab->save();

            $anjab->uraianTugas()->delete();
            foreach (array_values($data['uraian']) as $i => $row) {
                $anjab->uraianTugas()->create(['satuan_periode' => $row['satuan_periode'] ?? 'tahun'] + $row + ['urutan' => $i + 1]);
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
            'jabatanOptions' => Jabatan::where('is_active', true)->orderBy('jenis')->orderBy('nama')->get(['id', 'nama', 'jenis', 'kode', 'kelas_jabatan']),
            'instansiId' => $instansiId,
            'wkeDefault' => Referensi::WAKTU_KERJA_EFEKTIF,
            'informasiFields' => Referensi::INFORMASI_JABATAN,
            'periodeOptions' => Referensi::PERIODE_LABEL,
            'periodePerTahun' => Referensi::PERIODE_PER_TAHUN,
        ];
    }

    private function canEdit(): bool
    {
        return in_array(request()->user()->role?->value, ['admin', 'operator_instansi'], true);
    }

    private function existingCount(AnjabAbk $anjab): int
    {
        return Pegawai::where('unit_kerja_id', $anjab->unit_kerja_id)
            ->where('jabatan_id', $anjab->jabatan_id)->where('is_active', true)->count();
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
