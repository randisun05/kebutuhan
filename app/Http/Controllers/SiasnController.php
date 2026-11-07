<?php

namespace App\Http\Controllers;

use App\Jobs\SinkronSiasn;
use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\SiasnJabatanPeta;
use App\Models\SiasnSyncLog;
use App\Models\SiasnUnorPeta;
use App\Models\UnitKerja;
use App\Services\Siasn\SiasnClient;
use Illuminate\Http\Request;
use Throwable;

class SiasnController extends Controller
{
    public function index(SiasnClient $client)
    {
        $instansiOptions = $this->instansiOptions();
        $instansiId = $this->selectedInstansiId() ?? $instansiOptions->first()?->id;

        return inertia('Siasn/Index', [
            'configured' => $client->isConfigured(),
            'mode' => config('siasn.mode'),
            'baseUrl' => config('siasn.base_url'),
            'scheduled' => (bool) config('siasn.scheduled'),
            'instansiOptions' => $instansiOptions,
            'instansiId' => $instansiId,
            'instansi' => $instansiId ? Instansi::find($instansiId, ['id', 'nama', 'siasn_instansi_id']) : null,
            'pemetaan' => fn () => [
                'unit_total' => UnitKerja::where('instansi_id', $instansiId)->count(),
                'unit_terpetakan' => UnitKerja::where('instansi_id', $instansiId)->whereNotNull('siasn_unor_id')->count(),
                'jabatan_total' => Jabatan::count(),
                'jabatan_terpetakan' => Jabatan::whereNotNull('siasn_jabatan_id')->count(),
            ],
            'petaJabatan' => fn () => $this->petaDenganSaran(),
            'petaUnor' => fn () => $instansiId ? SiasnUnorPeta::with('unitKerja:id,nama')->where('instansi_id', $instansiId)
                ->orderByRaw('CASE WHEN unit_kerja_id IS NULL THEN 0 ELSE 1 END')->orderByDesc('jumlah_pegawai')->limit(500)->get() : [],
            'unitOptions' => fn () => $instansiId ? UnitKerja::flatTree((int) $instansiId)->where('is_active', true)->values() : [],
            'jabatanOptions' => fn () => $this->bolehMemetakan() ? Jabatan::where('is_active', true)->orderBy('nama')->get(['id', 'nama']) : [],
            'logs' => fn () => $this->scoped(SiasnSyncLog::query())
                ->with(['instansi:id,nama', 'user:id,name'])
                ->when($instansiId, fn ($q) => $q->where('instansi_id', $instansiId))
                ->latest()->limit(20)->get(),
        ]);
    }

    /** Petakan jabatan SIASN ke jabatan SIMONKEB (referensi nasional, khusus administrator). */
    public function petakanJabatan(Request $request, SiasnJabatanPeta $peta)
    {
        abort_unless($this->bolehMemetakan(), 403);
        $data = $request->validate(['jabatan_id' => 'nullable|exists:jabatans,id']);
        $peta->update(['jabatan_id' => $data['jabatan_id'] ?? null]);

        return back()->with('success', $peta->jabatan_id
            ? 'Pemetaan disimpan. Jalankan sinkron data pegawai agar pegawai terkait diperbarui.'
            : 'Pemetaan dihapus.');
    }

    /** Petakan unor SIASN ke unit kerja instansi (administrator dan operator instansi yang bersangkutan). */
    public function petakanUnor(Request $request, SiasnUnorPeta $petaUnor)
    {
        $this->authorizeInstansi($petaUnor->instansi_id);
        $data = $request->validate(['unit_kerja_id' => 'nullable|exists:unit_kerjas,id']);
        if (! empty($data['unit_kerja_id'])) {
            abort_unless(UnitKerja::where('id', $data['unit_kerja_id'])->where('instansi_id', $petaUnor->instansi_id)->exists(), 403, 'Unit bukan milik instansi ini.');
        }
        $petaUnor->update(['unit_kerja_id' => $data['unit_kerja_id'] ?? null]);

        return back()->with('success', $petaUnor->unit_kerja_id
            ? 'Pemetaan unor disimpan. Jalankan sinkron data pegawai agar pegawai terkait diperbarui.'
            : 'Pemetaan unor dihapus.');
    }

    /** Daftar pemetaan jabatan beserta saran jabatan SIMONKEB terdekat (kemiripan nama) untuk yang belum dipetakan. */
    private function petaDenganSaran()
    {
        $peta = SiasnJabatanPeta::with('jabatan:id,nama')
            ->orderByRaw('CASE WHEN jabatan_id IS NULL THEN 0 ELSE 1 END')->orderByDesc('jumlah_pegawai')->limit(500)->get();
        $belum = $peta->whereNull('jabatan_id');
        if ($belum->isEmpty()) {
            return $peta;
        }

        $normal = fn (string $t) => preg_replace('/\s+/', ' ', trim(mb_strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t))));
        $kandidat = Jabatan::where('is_active', true)->get(['id', 'nama'])->map(fn ($j) => ['id' => $j->id, 'nama' => $j->nama, 'n' => $normal($j->nama)]);

        return $peta->map(function (SiasnJabatanPeta $p) use ($normal, $kandidat) {
            $p->saran = null;
            if (! $p->jabatan_id && $p->nama_siasn) {
                $n = $normal($p->nama_siasn);
                $terbaik = $kandidat->map(function ($k) use ($n) {
                    similar_text($n, $k['n'], $persen);

                    return $k + ['skor' => (int) round($persen)];
                })->sortByDesc('skor')->first();
                if ($terbaik && $terbaik['skor'] >= 60) {
                    $p->saran = ['id' => $terbaik['id'], 'nama' => $terbaik['nama'], 'skor' => $terbaik['skor']];
                }
            }

            return $p;
        });
    }

    private function bolehMemetakan(): bool
    {
        return (bool) request()->user()?->isAdmin();
    }

    public function sync(Request $request, SiasnClient $client)
    {
        $data = $request->validate([
            'instansi_id' => 'required|exists:instansis,id',
            'jenis' => 'required|in:pegawai,unor',
            'nips' => 'nullable|string|max:20000',
        ]);
        $this->authorizeInstansi((int) $data['instansi_id']);

        if (! $client->isConfigured()) {
            return back()->with('error', 'Integrasi SIASN belum dikonfigurasi. Isi kredensial SIASN_* pada file .env.');
        }

        $nips = $data['jenis'] === 'pegawai' && ! empty($data['nips'])
            ? preg_split('/[\s,;]+/', trim($data['nips']), -1, PREG_SPLIT_NO_EMPTY)
            : null;

        SinkronSiasn::dispatch((int) $data['instansi_id'], $data['jenis'], $nips, $request->user()->id);

        return back()->with('success', 'Sinkronisasi SIASN dijalankan. Pantau hasilnya pada riwayat sinkronisasi.');
    }

    public function test(SiasnClient $client)
    {
        abort_unless(request()->user()->isAdmin(), 403);

        try {
            $client->testConnection();

            return back()->with('success', 'Koneksi SIASN berhasil: token APIM dan SSO diperoleh.');
        } catch (Throwable $e) {
            return back()->with('error', 'Koneksi SIASN gagal: '.$e->getMessage());
        }
    }

    public function log(SiasnSyncLog $log)
    {
        $this->authorizeInstansi($log->instansi_id);

        return response()->json($log->load(['instansi:id,nama', 'user:id,name']));
    }
}
