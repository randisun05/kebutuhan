<?php

namespace App\Http\Controllers;

use App\Models\Peringatan;
use App\Services\PeringatanService;
use Illuminate\Http\Request;

class PeringatanController extends Controller
{
    public function index()
    {
        $status = request('status', 'terbuka');
        $base = fn () => $this->scoped(Peringatan::query())
            ->when($this->selectedInstansiId(), fn ($q, $i) => $q->where('instansi_id', $i))
            ->when($status === 'terbuka', fn ($q) => $q->whereIn('status', ['aktif', 'ditindaklanjuti']), fn ($q) => $q->where('status', $status));

        $datas = $base()
            ->with(['instansi:id,nama', 'unitKerja:id,nama', 'penangan:id,name'])
            ->when(request('kode'), fn ($q, $k) => $q->where('kode', $k))
            ->when(request('tingkat'), fn ($q, $t) => $q->where('tingkat', $t))
            ->orderByRaw("CASE tingkat WHEN 'kritis' THEN 1 WHEN 'tinggi' THEN 2 WHEN 'sedang' THEN 3 ELSE 4 END")
            ->orderByDesc('pertama_terdeteksi_at')
            ->paginate(25)->withQueryString();

        return inertia('Peringatan/Index', [
            'datas' => $datas,
            'filters' => request()->only('kode', 'tingkat', 'instansi_id') + ['status' => $status],
            'aturan' => PeringatanService::ATURAN,
            'instansiOptions' => $this->instansiOptions(),
            'perTingkat' => $base()->selectRaw('tingkat, COUNT(*) as jumlah')->groupBy('tingkat')->pluck('jumlah', 'tingkat'),
            'perKode' => $base()->selectRaw('kode, COUNT(*) as jumlah')->groupBy('kode')->pluck('jumlah', 'kode'),
            'terakhirDeteksi' => Peringatan::max('terakhir_terdeteksi_at'),
            'canRun' => request()->user()->isAdmin(),
        ]);
    }

    public function tindakLanjut(Request $request, Peringatan $peringatan)
    {
        $this->authorizeInstansi($peringatan->instansi_id);
        abort_if(in_array($request->user()->role?->value, ['pimpinan'], true), 403);

        $data = $request->validate(['catatan' => 'required|string|max:2000']);
        $peringatan->update([
            'status' => 'ditindaklanjuti',
            'catatan_tindak_lanjut' => $data['catatan'],
            'ditangani_oleh' => $request->user()->id,
        ]);

        return back()->with('success', 'Tindak lanjut dicatat. Peringatan selesai otomatis ketika kondisinya teratasi.');
    }

    public function deteksi(PeringatanService $service)
    {
        abort_unless(request()->user()->isAdmin(), 403);
        $hasil = $service->deteksi();

        return back()->with('success', "Deteksi selesai: {$hasil['baru']} baru, {$hasil['aktif']} aktif, {$hasil['selesai']} selesai.");
    }
}
