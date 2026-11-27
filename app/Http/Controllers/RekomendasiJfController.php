<?php

namespace App\Http\Controllers;

use App\Models\ImporProfil;
use App\Models\InstansiAlias;
use App\Models\JfJabatanPokok;
use App\Models\KebutuhanFakta;
use App\Models\RekomendasiJf;
use App\Models\RekomendasiJfImpor;
use App\Models\SumberData;
use App\Services\RekomendasiJfImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rekomendasi BKN dan penetapan KemenPANRB untuk JF MASN (instansi × jabatan pokok × jenjang). Kolom angka mengikuti
 * daftar sumber di database, sehingga sumber baru muncul tanpa mengubah kode. Operator instansi hanya melihat
 * instansinya (yang aliasnya sudah dipetakan ke referensi instansi).
 */
class RekomendasiJfController extends Controller
{
    private const FOLDER = 'rekomendasi-jf-tmp';

    public function index()
    {
        $user = request()->user();
        $dasar = fn () => RekomendasiJf::query()->when($user->isScopedToInstansi(), fn (Builder $q) => $q->whereHas('alias.refInstansi', fn ($w) => $w->where('instansi_id', $user->instansi_id)));
        $sumber = $this->sumberImpor();

        $query = $dasar()->with('alias:id,nama,ref_instansi_id', 'jabatanPokok:id,nama');
        foreach ($sumber as $s) {
            // null = belum ada data; 0 = datanya nol
            $query->addSelect(['s_'.$s->kode => KebutuhanFakta::query()->selectRaw('SUM(nilai)')->whereColumn('rekomendasi_jf_id', 'rekomendasi_jf.id')
                ->where('is_terbaru', true)->where('sumber_id', $s->id)]);
        }
        $query->addSelect('rekomendasi_jf.*');

        $datas = $query
            ->when(request('q'), fn ($q, $s) => $q->whereHas('alias', fn ($w) => $w->where('nama', 'like', "%{$s}%")))
            ->when(request('jabatan_pokok_id'), fn ($q, $j) => $q->where('jabatan_pokok_id', $j))
            ->when(request('status_rekom'), fn ($q, $s) => $q->where('status_rekom', $s))
            ->when(request('status_menpan') === 'ditetapkan', fn ($q) => $q->where('status_menpan', 'ditetapkan'))
            ->when(request('status_menpan') === 'belum', fn ($q) => $q->whereNull('status_menpan'))
            ->when(request()->boolean('belum_cocok'), fn ($q) => $q->whereHas('alias', fn ($w) => $w->whereNull('ref_instansi_id')))
            ->join('instansi_alias as ia', 'ia.id', '=', 'rekomendasi_jf.alias_id')
            ->orderBy('ia.nama')->orderBy('jabatan_pokok_id')
            ->paginate(25)->withQueryString();

        $admin = $user->isAdmin();

        return inertia('RekomendasiJf/Index', [
            'datas' => $datas,
            'sumber' => $sumber->map(fn ($s) => ['kode' => $s->kode, 'nama' => $s->nama, 'jenis' => $s->jenis]),
            'filters' => request()->only('q', 'jabatan_pokok_id', 'status_rekom', 'status_menpan', 'belum_cocok'),
            'jabatanOptions' => JfJabatanPokok::where('is_active', true)->orderBy('urutan')->get(['id', 'nama']),
            'statusOptions' => RekomendasiJf::query()->whereNotNull('status_rekom')->distinct()->orderBy('status_rekom')->pluck('status_rekom'),
            'ringkasan' => [
                'baris' => $dasar()->count(),
                'instansi' => $dasar()->distinct()->count('alias_id'),
                'rekom_done' => $dasar()->where('status_rekom', 'DONE')->count(),
                'ditetapkan' => $dasar()->where('status_menpan', 'ditetapkan')->count(),
                'alias_belum_cocok' => $admin ? InstansiAlias::whereNull('ref_instansi_id')->count() : 0,
            ],
            'profilOptions' => $admin ? ImporProfil::where('is_active', true)->orderBy('nama')->get(['id', 'nama']) : [],
            'imporTerakhir' => RekomendasiJfImpor::with('user:id,name')->latest('id')->first(),
            'bolehImpor' => $admin,
            'batasUnggahMb' => $admin ? $this->batasUnggahMb() : null,
        ]);
    }

    public function show(RekomendasiJf $rekomendasi)
    {
        $user = request()->user();
        $rekomendasi->load('alias.refInstansi:id,nama,instansi_id', 'jabatanPokok.jenjangs', 'surats');
        if ($user->isScopedToInstansi()) {
            abort_unless($rekomendasi->alias->refInstansi?->instansi_id === $user->instansi_id, 403, 'Data ini bukan milik instansi Anda.');
        }

        $sumber = $this->sumberImpor();
        $terbaru = $rekomendasi->faktaTerbaru()->get()->groupBy('jenjang_id');
        $jenjang = $rekomendasi->jabatanPokok->jenjangs->where('is_active', true)->map(function ($j) use ($sumber, $terbaru) {
            $nilai = $terbaru->get($j->id, collect());

            return ['kode' => $j->kode, 'nama' => $j->nama, 'nilai' => $sumber->mapWithKeys(fn ($s) => [$s->kode => $nilai->firstWhere('sumber_id', $s->id)?->nilai])->all()];
        })->values();

        return inertia('RekomendasiJf/Show', [
            'data' => $rekomendasi,
            'sumber' => $sumber->map(fn ($s) => ['kode' => $s->kode, 'nama' => $s->nama, 'jenis' => $s->jenis]),
            'jenjang' => $jenjang,
            // versi lama angka (diganti oleh impor berikutnya)
            'riwayat' => $rekomendasi->fakta()->where('is_terbaru', false)->with('sumber:id,nama', 'jenjang:id,nama')->latest('id')->limit(100)->get()
                ->map(fn ($f) => ['sumber' => $f->sumber->nama, 'jenjang' => $f->jenjang->nama, 'nilai' => $f->nilai, 'dicatat' => $f->created_at]),
        ]);
    }

    /** Baca dan validasi berkas Excel tanpa menyimpan data; berkas ditahan sementara untuk konfirmasi. */
    public function pratinjau(Request $request, RekomendasiJfImportService $service)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $request->validate(['file' => 'required|file|mimes:xlsx|max:30720', 'profil_id' => 'nullable|exists:impor_profil,id']);
        $this->bersihkanSementara();

        $token = Str::random(32);
        $path = $request->file('file')->storeAs(self::FOLDER, $token.'.xlsx', 'local');

        try {
            $hasil = $service->baca(Storage::disk('local')->path($path), $request->filled('profil_id') ? ImporProfil::find($request->integer('profil_id')) : null);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'token' => $token, 'profil_id' => $hasil['profil_id'], 'nama_berkas' => $request->file('file')->getClientOriginalName(),
            'ringkas' => $hasil['ringkas'], 'peringatan' => array_slice($hasil['peringatan'], 0, 30),
            'sumber' => SumberData::pluck('nama', 'kode'),
        ]);
    }

    public function impor(Request $request, RekomendasiJfImportService $service)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'token' => ['required', 'regex:/^[A-Za-z0-9]{32}$/'], 'nama_berkas' => 'nullable|string|max:255', 'profil_id' => 'nullable|exists:impor_profil,id',
        ]);
        $path = self::FOLDER.'/'.$data['token'].'.xlsx';
        abort_unless(Storage::disk('local')->exists($path), 422, 'Berkas pratinjau sudah kedaluwarsa. Unggah ulang.');

        try {
            $hasil = $service->baca(Storage::disk('local')->path($path), isset($data['profil_id']) ? ImporProfil::find($data['profil_id']) : null);
            $impor = $service->simpan($hasil, $request->user(), $data['nama_berkas'] ?? 'rekap.xlsx');
        } finally {
            Storage::disk('local')->delete($path);
        }

        return back()->with('success', "Impor rekomendasi JF MASN: {$impor->dibuat} baris dibuat, {$impor->diperbarui} diperbarui, {$impor->fakta_baru} angka baru/berubah, ".count($impor->peringatan ?? []).' peringatan.');
    }

    /** Sumber angka aktif yang berasal dari impor (kolom dinamis di daftar). */
    private function sumberImpor()
    {
        return SumberData::where('is_active', true)->where('adapter', 'fakta')->orderBy('urutan')->get();
    }

    /** Batas unggah efektif server (terkecil antara upload_max_filesize dan post_max_size), dalam MB. */
    private function batasUnggahMb(): float
    {
        $ke_mb = function (string $ini): float {
            $n = (float) $ini;
            $satuan = strtolower(substr(trim($ini), -1));

            return match ($satuan) {
                'g' => $n * 1024, 'k' => $n / 1024, 'm' => $n, default => $n / 1048576,
            };
        };

        return round(min($ke_mb((string) ini_get('upload_max_filesize')), $ke_mb((string) ini_get('post_max_size')), 30), 1);
    }

    private function bersihkanSementara(): void
    {
        foreach (Storage::disk('local')->files(self::FOLDER) as $berkas) {
            if (Storage::disk('local')->lastModified($berkas) < now()->subDay()->getTimestamp()) {
                Storage::disk('local')->delete($berkas);
            }
        }
    }
}
