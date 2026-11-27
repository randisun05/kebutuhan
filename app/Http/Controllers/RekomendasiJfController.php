<?php

namespace App\Http\Controllers;

use App\Models\RefInstansiBkn;
use App\Models\RekomendasiJf;
use App\Models\RekomendasiJfImpor;
use App\Models\RekomendasiJfJenjang;
use App\Services\RekomendasiJfImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rekomendasi BKN dan penetapan KemenPANRB untuk JF MASN (per instansi × jabatan pokok × jenjang),
 * diimpor dari Excel rekap tim data. Operator instansi hanya melihat instansinya (yang sudah dipetakan).
 */
class RekomendasiJfController extends Controller
{
    private const FOLDER = 'rekomendasi-jf-tmp';

    public function index()
    {
        $user = request()->user();
        $dasar = fn () => RekomendasiJf::query()->when($user->isScopedToInstansi(),
            fn ($q) => $q->whereHas('refInstansi', fn ($w) => $w->where('instansi_id', $user->instansi_id)));

        $datas = $dasar()->with('refInstansi:id,nama')
            ->withSum('jenjangs as bezetting', 'bezetting_rekom')->withSum('jenjangs as rekom_bkn', 'rekom_bkn')->withSum('jenjangs as penetapan_menpan', 'penetapan_menpan')
            ->when(request('q'), fn ($q, $s) => $q->where('nama_instansi', 'like', "%{$s}%"))
            ->when(request('jabatan_pokok'), fn ($q, $j) => $q->where('jabatan_pokok', $j))
            ->when(request('status_rekom'), fn ($q, $s) => $q->where('status_rekom', $s))
            ->when(request('status_menpan') === 'ditetapkan', fn ($q) => $q->where('status_menpan', 'ditetapkan'))
            ->when(request('status_menpan') === 'belum', fn ($q) => $q->whereNull('status_menpan'))
            ->when(request()->boolean('belum_cocok'), fn ($q) => $q->whereNull('ref_instansi_id'))
            ->orderBy('nama_instansi')->orderBy('jabatan_pokok')
            ->paginate(25)->withQueryString();

        $admin = $user->isAdmin();
        $belumCocok = $admin ? RekomendasiJf::whereNull('ref_instansi_id')->selectRaw('nama_instansi_normal, MIN(nama_instansi) as nama')
            ->groupBy('nama_instansi_normal')->orderBy('nama')->limit(200)->get() : collect();

        return inertia('RekomendasiJf/Index', [
            'datas' => $datas,
            'filters' => request()->only('q', 'jabatan_pokok', 'status_rekom', 'status_menpan', 'belum_cocok'),
            'jabatanOptions' => array_keys(RekomendasiJf::JABATAN),
            'statusOptions' => RekomendasiJf::query()->whereNotNull('status_rekom')->distinct()->orderBy('status_rekom')->pluck('status_rekom'),
            'ringkasan' => [
                'baris' => $dasar()->count(),
                'instansi' => $dasar()->distinct()->count('nama_instansi_normal'),
                'rekom_done' => $dasar()->where('status_rekom', 'DONE')->count(),
                'ditetapkan' => $dasar()->where('status_menpan', 'ditetapkan')->count(),
                'belum_cocok' => $admin ? RekomendasiJf::whereNull('ref_instansi_id')->distinct()->count('nama_instansi_normal') : 0,
            ],
            'belumCocok' => $belumCocok,
            'refOptions' => $admin && $belumCocok->isNotEmpty() ? RefInstansiBkn::orderBy('nama')->get(['id', 'nama']) : [],
            'imporTerakhir' => RekomendasiJfImpor::with('user:id,name')->latest('id')->first(),
            'bolehImpor' => $admin,
            'batasUnggahMb' => $admin ? $this->batasUnggahMb() : null,
        ]);
    }

    public function show(RekomendasiJf $rekomendasi)
    {
        $user = request()->user();
        if ($user->isScopedToInstansi()) {
            abort_unless($rekomendasi->refInstansi?->instansi_id === $user->instansi_id, 403, 'Data ini bukan milik instansi Anda.');
        }
        $rekomendasi->load('refInstansi:id,nama,instansi_id', 'jenjangs', 'surats');

        $urutan = RekomendasiJf::JENJANG[$rekomendasi->rumpun];

        return inertia('RekomendasiJf/Show', [
            'data' => $rekomendasi,
            'jenjang' => collect($urutan)->filter()->map(function ($kode) use ($rekomendasi) {
                /** @var RekomendasiJfJenjang|null $j */
                $j = $rekomendasi->jenjangs->firstWhere('jenjang', $kode);

                return [
                    'kode' => $kode, 'bezetting_rekom' => $j?->bezetting_rekom, 'rekom_bkn' => $j?->rekom_bkn, 'penetapan_menpan' => $j?->penetapan_menpan,
                    'lowongan' => $j?->lowongan,
                    'selisih_menpan' => $j && $j->penetapan_menpan !== null && $j->rekom_bkn !== null ? $j->penetapan_menpan - $j->rekom_bkn : null,
                ];
            })->values(),
        ]);
    }

    /** Baca dan validasi berkas Excel tanpa menyimpan data; berkas ditahan sementara untuk konfirmasi. */
    public function pratinjau(Request $request, RekomendasiJfImportService $service)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $request->validate(['file' => 'required|file|mimes:xlsx|max:30720']);
        $this->bersihkanSementara();

        $token = Str::random(32);
        $path = $request->file('file')->storeAs(self::FOLDER, $token.'.xlsx', 'local');

        try {
            $hasil = $service->baca(Storage::disk('local')->path($path));
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'token' => $token, 'nama_berkas' => $request->file('file')->getClientOriginalName(),
            'ringkas' => $hasil['ringkas'], 'peringatan' => array_slice($hasil['peringatan'], 0, 30),
        ]);
    }

    public function impor(Request $request, RekomendasiJfImportService $service)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['token' => ['required', 'regex:/^[A-Za-z0-9]{32}$/'], 'nama_berkas' => 'nullable|string|max:255']);
        $path = self::FOLDER.'/'.$data['token'].'.xlsx';
        abort_unless(Storage::disk('local')->exists($path), 422, 'Berkas pratinjau sudah kedaluwarsa. Unggah ulang.');

        try {
            $impor = $service->simpan($service->baca(Storage::disk('local')->path($path)), $request->user(), $data['nama_berkas'] ?? 'rekap.xlsx');
        } finally {
            Storage::disk('local')->delete($path);
        }

        return back()->with('success', "Impor rekomendasi JF MASN: {$impor->dibuat} baris dibuat, {$impor->diperbarui} diperbarui, ".count($impor->peringatan ?? []).' peringatan.');
    }

    /** Petakan nama instansi Excel yang belum cocok ke referensi instansi BKN (administrator). */
    public function petakanInstansi(Request $request, RekomendasiJfImportService $service)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['nama_normal' => 'required|string|max:191', 'ref_instansi_id' => 'nullable|exists:ref_instansi_bkn,id']);
        $baris = RekomendasiJf::where('nama_instansi_normal', $data['nama_normal'])->first();
        abort_unless($baris, 404);

        $service->petakanInstansi($data['nama_normal'], $baris->nama_instansi, $data['ref_instansi_id'] ?? null);

        return back()->with('success', 'Pemetaan instansi disimpan.');
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
