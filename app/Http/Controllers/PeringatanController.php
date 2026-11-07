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
            // tampilan ringkas: peringatan dikelompokkan per unit kerja (mengurangi kebisingan banyak peringatan per posisi)
            'kelompok' => request('kelompok') === 'unit' ? $this->kelompokPerUnit($base) : null,
            'filters' => request()->only('kode', 'tingkat', 'instansi_id', 'kelompok') + ['status' => $status],
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

    /** Kelompokkan peringatan (sesuai filter jenis/tingkat) per unit kerja; yang tidak terkait unit dikelompokkan per instansi. */
    private function kelompokPerUnit(\Closure $base): array
    {
        $urut = ['kritis' => 1, 'tinggi' => 2, 'sedang' => 3, 'rendah' => 4];

        return $base()
            ->when(request('kode'), fn ($q, $k) => $q->where('kode', $k))
            ->when(request('tingkat'), fn ($q, $t) => $q->where('tingkat', $t))
            ->with(['instansi:id,nama', 'unitKerja:id,nama'])
            ->limit(3000)->get(['id', 'instansi_id', 'unit_kerja_id', 'kode', 'tingkat', 'judul'])
            ->groupBy(fn ($p) => $p->unit_kerja_id ? 'u'.$p->unit_kerja_id : 'i'.$p->instansi_id)
            ->map(function ($g) use ($urut) {
                $p = $g->first();
                $tertinggi = $g->sortBy(fn ($x) => $urut[$x->tingkat] ?? 9)->first()->tingkat;

                return [
                    'unit_id' => $p->unit_kerja_id, 'nama' => $p->unitKerja?->nama ?? '(tingkat instansi)', 'instansi' => $p->instansi?->nama,
                    'total' => $g->count(), 'tertinggi' => $tertinggi, 'per_tingkat' => $g->countBy('tingkat')->all(),
                    'contoh' => $g->sortBy(fn ($x) => $urut[$x->tingkat] ?? 9)->take(3)->pluck('judul')->values()->all(),
                ];
            })
            ->sortBy([fn ($a, $b) => ($urut[$a['tertinggi']] ?? 9) <=> ($urut[$b['tertinggi']] ?? 9), fn ($a, $b) => $b['total'] <=> $a['total']])
            ->values()->all();
    }
}
