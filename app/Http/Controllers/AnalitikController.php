<?php

namespace App\Http\Controllers;

use App\Models\Peringatan;
use App\Models\UnitKerja;
use App\Services\HistoriService;
use App\Services\MonitoringService;
use App\Support\Referensi;
use Illuminate\Support\Facades\DB;

/** Dashboard data: tren, pergerakan, komposisi pegawai, pensiun, dan peta panas pemenuhan. */
class AnalitikController extends Controller
{
    public function __invoke(MonitoringService $monitoring, HistoriService $histori)
    {
        $instansiId = $this->selectedInstansiId();
        if ($instansiId) {
            $this->authorizeInstansi($instansiId);
        }
        $filters = array_filter(['instansi_id' => $instansiId]);

        return inertia('Analitik/Index', [
            'filters' => ['instansi_id' => $instansiId],
            'instansiOptions' => $this->instansiOptions(),
            'summary' => fn () => $monitoring->summary($filters),
            'tren' => fn () => $histori->tren($filters, 12),
            'pergerakan' => fn () => $histori->pergerakanBulanan($filters, 12),
            'komposisi' => fn () => $this->komposisi($instansiId),
            'pensiunPerTahun' => fn () => $this->pensiunPerTahun($instansiId),
            'heatmap' => fn () => $this->heatmap($monitoring, $instansiId),
            'peringatanTingkat' => fn () => $this->scoped(Peringatan::query())->whereIn('status', ['aktif', 'ditindaklanjuti'])
                ->when($instansiId, fn ($q) => $q->where('instansi_id', $instansiId))
                ->selectRaw('tingkat, COUNT(*) as jumlah')->groupBy('tingkat')->pluck('jumlah', 'tingkat'),
            'updatedAt' => fn () => now()->toIso8601String(),
        ]);
    }

    private function pegawai(?int $instansiId)
    {
        return DB::table('pegawais as p')->where('p.is_active', true)
            ->when($instansiId, fn ($q) => $q->where('p.instansi_id', $instansiId))
            ->when(request()->user()->isScopedToInstansi(), fn ($q) => $q->where('p.instansi_id', request()->user()->instansi_id));
    }

    private function komposisi(?int $instansiId): array
    {
        $jenis = $this->pegawai($instansiId)->join('jabatans as j', 'j.id', '=', 'p.jabatan_id')
            ->groupBy('j.jenis')->selectRaw('j.jenis as k, COUNT(*) as n')->pluck('n', 'k');

        $status = $this->pegawai($instansiId)->groupBy('p.status_kepegawaian')->selectRaw('p.status_kepegawaian as k, COUNT(*) as n')->pluck('n', 'k');

        $golongan = $this->pegawai($instansiId)->groupBy('p.golongan')->selectRaw('p.golongan as k, COUNT(*) as n')->pluck('n', 'k')
            ->groupBy(fn ($n, $k) => $k ? 'Gol. '.explode('/', $k)[0] : 'Tidak diketahui', true)->map->sum();

        $pendidikan = $this->pegawai($instansiId)->groupBy('p.pendidikan')->selectRaw('p.pendidikan as k, COUNT(*) as n')->pluck('n', 'k');

        $tahunIni = (int) now()->format('Y');
        $usia = $this->pegawai($instansiId)->whereNotNull('p.tanggal_lahir')
            ->groupByRaw('SUBSTR(p.tanggal_lahir, 1, 4)')->selectRaw('SUBSTR(p.tanggal_lahir, 1, 4) as th, COUNT(*) as n')->pluck('n', 'th')
            ->groupBy(function ($n, $th) use ($tahunIni) {
                $u = $tahunIni - (int) $th;

                return match (true) {
                    $u < 30 => '< 30', $u < 40 => '30–39', $u < 50 => '40–49', $u < 55 => '50–54', default => '≥ 55',
                };
            }, true)->map->sum();

        $urutUsia = ['< 30', '30–39', '40–49', '50–54', '≥ 55'];

        return [
            'jenis' => collect(Referensi::JENIS_JABATAN)->map(fn ($l, $k) => ['label' => $l, 'jumlah' => (int) ($jenis[$k] ?? 0)])->values(),
            'status' => collect(['pns' => 'PNS', 'pppk' => 'PPPK'])->map(fn ($l, $k) => ['label' => $l, 'jumlah' => (int) ($status[$k] ?? 0)])->values(),
            'golongan' => $golongan->sortKeys()->map(fn ($n, $k) => ['label' => $k, 'jumlah' => (int) $n])->values(),
            'pendidikan' => $pendidikan->sortDesc()->map(fn ($n, $k) => ['label' => $k ?: 'Tidak diketahui', 'jumlah' => (int) $n])->values(),
            'usia' => collect($urutUsia)->map(fn ($k) => ['label' => $k, 'jumlah' => (int) ($usia[$k] ?? 0)]),
        ];
    }

    private function pensiunPerTahun(?int $instansiId): array
    {
        $mulai = (int) now()->format('Y');
        $rows = $this->pegawai($instansiId)->whereNotNull('p.tmt_pensiun')
            ->where('p.tmt_pensiun', '>=', now()->toDateString())
            ->where('p.tmt_pensiun', '<=', ($mulai + 4).'-12-31')
            ->groupByRaw('SUBSTR(p.tmt_pensiun, 1, 4)')->selectRaw('SUBSTR(p.tmt_pensiun, 1, 4) as th, COUNT(*) as n')->pluck('n', 'th');

        return collect(range($mulai, $mulai + 4))->map(fn ($t) => ['tahun' => $t, 'jumlah' => (int) ($rows[(string) $t] ?? 0)])->all();
    }

    /**
     * Peta panas % pemenuhan: baris = instansi (nasional) atau unit eselon II ke atas (instansi),
     * kolom = jenis jabatan.
     */
    private function heatmap(MonitoringService $monitoring, ?int $instansiId): array
    {
        $kolom = collect(Referensi::JENIS_JABATAN)->except(['jpt_utama', 'jpt_madya'])->all();

        if ($instansiId) {
            $units = UnitKerja::where('instansi_id', $instansiId)->where(fn ($q) => $q->whereNull('parent_id')
                ->orWhereIn('parent_id', UnitKerja::where('instansi_id', $instansiId)->whereNull('parent_id')->pluck('id')))
                ->orderBy('urutan')->get(['id', 'nama']);
            $barisDef = $units->map(fn ($u) => ['id' => $u->id, 'nama' => $u->nama, 'filters' => ['instansi_id' => $instansiId, 'unit_ids' => UnitKerja::descendantIds($u->id)]]);
        } else {
            $barisDef = $this->instansiOptions()->map(fn ($i) => ['id' => $i->id, 'nama' => $i->nama, 'filters' => ['instansi_id' => $i->id]]);
        }

        $baris = $barisDef->map(function ($b) use ($monitoring, $kolom) {
            $perJenis = $monitoring->byJenisJabatan($b['filters'])->keyBy('jenis');

            return [
                'id' => $b['id'],
                'nama' => $b['nama'],
                'sel' => collect($kolom)->map(fn ($l, $k) => [
                    'persen' => $perJenis[$k]['persentase'] ?? null,
                    'kebutuhan' => $perJenis[$k]['kebutuhan'] ?? 0,
                    'existing' => $perJenis[$k]['existing'] ?? 0,
                ])->all(),
            ];
        })->values();

        return ['kolom' => $kolom, 'baris' => $baris, 'mode' => $instansiId ? 'unit' : 'instansi'];
    }
}
