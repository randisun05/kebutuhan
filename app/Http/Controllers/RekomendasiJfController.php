<?php

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Models\ImporProfil;
use App\Models\InstansiAlias;
use App\Models\InstansiPembina;
use App\Models\JfJabatanPokok;
use App\Models\KebutuhanFakta;
use App\Models\RekomendasiJf;
use App\Models\RekomendasiJfImpor;
use App\Models\SumberData;
use App\Services\RekomendasiJfImportService;
use App\Services\WawasanRekomendasiJfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
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
        $pembinaOptions = $this->pembinaDapatDiakses($user);
        $pembinaId = $this->pembinaTerpilih($pembinaOptions);
        $dasar = fn () => RekomendasiJf::query()->untukPembina($pembinaId)
            ->when($user->isScopedToInstansi(), fn (Builder $q) => $q->whereHas('alias.refInstansi', fn ($w) => $w->where('instansi_id', $user->instansi_id)));
        $sumber = $this->sumberImpor($pembinaId);

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
        $bolehImpor = $admin || $user->isPembina();

        return inertia('RekomendasiJf/Index', [
            'datas' => $datas,
            'sumber' => $sumber->map(fn ($s) => ['kode' => $s->kode, 'nama' => $s->nama, 'jenis' => $s->jenis]),
            'pembinaOptions' => $pembinaOptions->map(fn ($p) => ['id' => $p->id, 'nama' => $p->nama])->values(),
            'pembinaId' => $pembinaId,
            'filters' => request()->only('q', 'jabatan_pokok_id', 'status_rekom', 'status_menpan', 'belum_cocok', 'pembina_id'),
            'jabatanOptions' => JfJabatanPokok::where('pembina_id', $pembinaId)->where('is_active', true)->orderBy('urutan')->get(['id', 'nama']),
            'statusOptions' => RekomendasiJf::query()->untukPembina($pembinaId)->whereNotNull('status_rekom')->distinct()->orderBy('status_rekom')->pluck('status_rekom'),
            'ringkasan' => [
                'baris' => $dasar()->count(),
                'instansi' => $dasar()->distinct()->count('alias_id'),
                'rekom_done' => $dasar()->where('status_rekom', 'DONE')->count(),
                'ditetapkan' => $dasar()->where('status_menpan', 'ditetapkan')->count(),
                'alias_belum_cocok' => $admin ? InstansiAlias::whereNull('ref_instansi_id')->count() : 0,
            ],
            'profilOptions' => $bolehImpor ? ImporProfil::where('pembina_id', $pembinaId)->where('is_active', true)->orderBy('nama')->get(['id', 'nama']) : [],
            'imporTerakhir' => RekomendasiJfImpor::with('user:id,name')->whereHas('profil', fn ($q) => $q->where('pembina_id', $pembinaId))->latest('id')->first(),
            'bolehImpor' => $bolehImpor,
            'batasUnggahMb' => $bolehImpor ? $this->batasUnggahMb() : null,
        ]);
    }

    /** Panel wawasan: sasaran penataan, jalur tersumbat, peluang pendampingan, antrean Menpan, dan pola pemotongan. */
    public function wawasan(WawasanRekomendasiJfService $service)
    {
        $user = request()->user();
        abort_if($user->isScopedToInstansi(), 403, 'Panel wawasan untuk pembina, bukan untuk operator instansi.');
        $pembinaOptions = $this->pembinaDapatDiakses($user);
        $pembinaId = $this->pembinaTerpilih($pembinaOptions);
        $w = $pembinaId ? $service->hitung($pembinaId) : ['lengkap' => false, 'peran' => []];

        // daftar panjang dipotong di layar; seluruhnya tersedia lewat unduh Excel
        if ($w['lengkap']) {
            $b = WawasanRekomendasiJfService::BATAS_LAYAR;
            foreach (['sasaran', 'tersumbat', 'pendampingan', 'antrean'] as $panel) {
                $w[$panel]['total_daftar'] = count($w[$panel]['daftar']);
                $w[$panel]['daftar'] = array_slice($w[$panel]['daftar'], 0, $b);
            }
        }

        return inertia('RekomendasiJf/Wawasan', [
            'w' => $w, 'pembinaOptions' => $pembinaOptions->map(fn ($p) => ['id' => $p->id, 'nama' => $p->nama])->values(), 'pembinaId' => $pembinaId,
            'batasLayar' => WawasanRekomendasiJfService::BATAS_LAYAR,
        ]);
    }

    /** Unduh daftar lengkap salah satu panel wawasan ke Excel. */
    public function wawasanUnduh(string $panel, WawasanRekomendasiJfService $service)
    {
        $user = request()->user();
        abort_if($user->isScopedToInstansi(), 403);
        $pembinaId = $this->pembinaTerpilih($this->pembinaDapatDiakses($user));
        abort_unless($pembinaId, 404);
        $w = $service->hitung($pembinaId);
        abort_unless($w['lengkap'], 404);

        [$judul, $kolom, $baris] = match ($panel) {
            'sasaran' => ['sasaran-penataan', ['Instansi', 'Jenis', 'Pokja', 'Bezetting', 'Rekomendasi', 'Kategori'],
                array_map(fn ($r) => [$r['instansi'], $r['jenis'], $r['pokja'], $r['bezetting'], $r['rekom'], $r['kategori'] === 'nol' ? 'Nol' : 'Kurang'], $w['sasaran']['daftar'])],
            'tersumbat' => ['jalur-tersumbat', ['Instansi', 'Jenis', 'Jabatan', 'Jenjang bawah', 'Dua jenjang teratas'],
                array_map(fn ($r) => [$r['instansi'], $r['jenis'], $r['jabatan'], $r['bawah'], $r['atas']], $w['tersumbat']['daftar'])],
            'pendampingan' => ['peluang-pendampingan', ['Instansi', 'Jenis', 'Pokja', 'Belum usul'],
                array_map(fn ($r) => [$r['instansi'], $r['jenis'], $r['pokja'], $r['belum_usul']], $w['pendampingan']['daftar'])],
            'antrean' => ['antrean-penetapan', ['Instansi', 'Jenis', 'Jabatan', 'Nomor surat rekomendasi', 'Tanggal surat', 'Umur (hari)'],
                array_map(fn ($r) => [$r['instansi'], $r['jenis'], $r['jabatan'], $r['nomor'], $r['tanggal'], $r['umur_hari']], $w['antrean']['daftar'])],
            default => abort(404),
        };

        return Excel::download(new ArrayExport($kolom, $baris), "wawasan-{$judul}-".now()->format('Ymd').'.xlsx');
    }

    public function show(RekomendasiJf $rekomendasi)
    {
        $user = request()->user();
        $rekomendasi->load('alias.refInstansi:id,nama,instansi_id', 'jabatanPokok.jenjangs', 'surats');
        abort_unless($user->canAccessPembina($rekomendasi->jabatanPokok->pembina_id), 403, 'Data ini bukan hak instansi pembina Anda.');
        if ($user->isScopedToInstansi()) {
            abort_unless($rekomendasi->alias->refInstansi?->instansi_id === $user->instansi_id, 403, 'Data ini bukan milik instansi Anda.');
        }

        $sumber = $this->sumberImpor($rekomendasi->jabatanPokok->pembina_id);
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
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isPembina(), 403);
        $request->validate(['file' => 'required|file|mimes:xlsx|max:30720', 'profil_id' => 'required|exists:impor_profil,id']);
        $profil = $this->profilBolehDipakai($user, $request->integer('profil_id'));
        $this->bersihkanSementara();

        $token = Str::random(32);
        $path = $request->file('file')->storeAs(self::FOLDER, $token.'.xlsx', 'local');

        try {
            $hasil = $service->baca(Storage::disk('local')->path($path), $profil);
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
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isPembina(), 403);
        $data = $request->validate([
            'token' => ['required', 'regex:/^[A-Za-z0-9]{32}$/'], 'nama_berkas' => 'nullable|string|max:255', 'profil_id' => 'required|exists:impor_profil,id',
        ]);
        $profil = $this->profilBolehDipakai($user, (int) $data['profil_id']);
        $path = self::FOLDER.'/'.$data['token'].'.xlsx';
        abort_unless(Storage::disk('local')->exists($path), 422, 'Berkas pratinjau sudah kedaluwarsa. Unggah ulang.');

        try {
            $hasil = $service->baca(Storage::disk('local')->path($path), $profil);
            $impor = $service->simpan($hasil, $request->user(), $data['nama_berkas'] ?? 'rekap.xlsx');
        } finally {
            Storage::disk('local')->delete($path);
        }

        return back()->with('success', "Impor rekomendasi JF MASN: {$impor->dibuat} baris dibuat, {$impor->diperbarui} diperbarui, {$impor->fakta_baru} angka baru/berubah, ".count($impor->peringatan ?? []).' peringatan.');
    }

    /** Sumber angka aktif milik satu pembina yang berasal dari impor (kolom dinamis di daftar). */
    private function sumberImpor(?int $pembinaId)
    {
        return SumberData::where('pembina_id', $pembinaId)->where('is_active', true)->where('adapter', 'fakta')->orderBy('urutan')->get();
    }

    /** Instansi pembina yang boleh dilihat pengguna: pengguna pembina hanya pembinanya sendiri. */
    private function pembinaDapatDiakses($user)
    {
        return InstansiPembina::where('is_active', true)->when($user->isScopedToPembina(), fn ($q) => $q->where('id', $user->pembina_id))->orderBy('nama')->get();
    }

    /** Pembina yang sedang dilihat: dari parameter bila boleh diakses, jika tidak yang pertama. */
    private function pembinaTerpilih($opsi): ?int
    {
        $diminta = request()->integer('pembina_id');

        return $opsi->contains('id', $diminta) ? $diminta : $opsi->first()?->id;
    }

    private function profilBolehDipakai($user, int $profilId): ImporProfil
    {
        $profil = ImporProfil::findOrFail($profilId);
        abort_unless($user->canAccessPembina($profil->pembina_id), 403, 'Profil impor ini bukan hak instansi pembina Anda.');

        return $profil;
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
