<?php

namespace App\Http\Controllers;

use App\Jobs\SinkronSiasn;
use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\SiasnSyncLog;
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
            'logs' => fn () => $this->scoped(SiasnSyncLog::query())
                ->with(['instansi:id,nama', 'user:id,name'])
                ->when($instansiId, fn ($q) => $q->where('instansi_id', $instansiId))
                ->latest()->limit(20)->get(),
        ]);
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
