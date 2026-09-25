<?php

namespace App\Http\Controllers;

use App\Enums\UsulanStatus;
use App\Models\Penetapan;
use App\Models\Peringatan;
use App\Models\Usulan;
use App\Services\MonitoringService;
use App\Support\Referensi;

class DashboardController extends Controller
{
    public function __invoke(MonitoringService $monitoring)
    {
        $filters = array_filter([
            'instansi_id' => $this->selectedInstansiId(),
            'jenis' => request('jenis'),
        ]);

        $usulanPerStatus = $this->scoped(Usulan::query())
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return inertia('Dashboard/Index', [
            'filters' => $filters,
            'instansiOptions' => $this->instansiOptions(),
            'jenisOptions' => Referensi::options(Referensi::JENIS_JABATAN),
            'summary' => fn () => $monitoring->summary($filters),
            'perInstansi' => fn () => $monitoring->byInstansi($filters)
                ->sortByDesc(fn ($r) => $r['kurang'] + $r['lebih'])->values(),
            'perJenis' => fn () => $monitoring->byJenisJabatan($filters),
            'usulan' => fn () => collect(UsulanStatus::cases())->map(fn ($s) => [
                'status' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'jumlah' => (int) ($usulanPerStatus[$s->value] ?? 0),
            ]),
            'penetapanTerbaru' => fn () => $this->scoped(Penetapan::query())
                ->with('instansi:id,nama')
                ->latest('tanggal_sk')->limit(5)
                ->get(['id', 'usulan_id', 'instansi_id', 'nomor_sk', 'tanggal_sk', 'total_ditetapkan']),
            'peringatanTerbaru' => fn () => $this->scoped(Peringatan::query())->where('status', 'aktif')
                ->whereIn('tingkat', ['kritis', 'tinggi'])
                ->when($filters['instansi_id'] ?? null, fn ($q, $i) => $q->where('instansi_id', $i))
                ->orderByRaw("CASE tingkat WHEN 'kritis' THEN 1 ELSE 2 END")->latest('pertama_terdeteksi_at')
                ->limit(5)->get(['id', 'kode', 'tingkat', 'judul', 'pesan', 'url']),
            'updatedAt' => fn () => now()->toIso8601String(),
        ]);
    }
}
