<?php

namespace App\Services;

/**
 * Saran redistribusi pegawai di dalam satu instansi: untuk jabatan yang sama,
 * kelebihan pegawai di satu unit dipasangkan dengan kekurangan di unit lain
 * (greedy: kelebihan terbesar ke kekurangan terbesar). Hanya posisi yang memiliki
 * ABK final yang dihitung, sehingga kelebihan benar-benar terukur.
 */
class RedistribusiService
{
    public function __construct(private MonitoringService $monitoring) {}

    public function saran(int $instansiId, array $filters = []): array
    {
        $positions = $this->monitoring->positionsQuery(['instansi_id' => $instansiId] + $filters)
            ->join('unit_kerjas as uk', 'uk.id', '=', 'p.unit_kerja_id')
            ->where('p.kebutuhan', '>', 0)
            ->select('p.*', 'uk.nama as unit_nama')
            ->get()
            ->map(fn ($p) => $this->monitoring->decoratePosition((array) $p));

        $saran = collect();
        $sisaKurang = collect();

        foreach ($positions->groupBy('jabatan_id') as $rows) {
            $lebih = $rows->filter(fn ($p) => $p['selisih'] > 0)->sortByDesc('selisih')->map(fn ($p) => $p + ['sisa' => $p['selisih']])->values()->all();
            $kurang = $rows->filter(fn ($p) => $p['selisih'] < 0)->sortBy('selisih')->map(fn ($p) => $p + ['sisa' => -$p['selisih']])->values()->all();

            $i = $j = 0;
            while ($i < count($lebih) && $j < count($kurang)) {
                $jumlah = min($lebih[$i]['sisa'], $kurang[$j]['sisa']);
                $saran->push([
                    'jabatan_id' => $lebih[$i]['jabatan_id'],
                    'jabatan_nama' => $lebih[$i]['jabatan_nama'],
                    'jenis_label' => $lebih[$i]['jenis_label'],
                    'dari_unit_id' => $lebih[$i]['unit_kerja_id'],
                    'dari_unit' => $lebih[$i]['unit_nama'],
                    'dari_selisih' => $lebih[$i]['selisih'],
                    'ke_unit_id' => $kurang[$j]['unit_kerja_id'],
                    'ke_unit' => $kurang[$j]['unit_nama'],
                    'ke_selisih' => $kurang[$j]['selisih'],
                    'jumlah' => $jumlah,
                ]);
                $lebih[$i]['sisa'] -= $jumlah;
                $kurang[$j]['sisa'] -= $jumlah;
                if ($lebih[$i]['sisa'] === 0) {
                    $i++;
                }
                if ($kurang[$j]['sisa'] === 0) {
                    $j++;
                }
            }

            foreach (array_slice($kurang, $j) as $k) {
                if ($k['sisa'] > 0) {
                    $sisaKurang->push(['jabatan_nama' => $k['jabatan_nama'], 'unit_nama' => $k['unit_nama'], 'jumlah' => $k['sisa']]);
                }
            }
        }

        return [
            'saran' => $saran->sortByDesc('jumlah')->values()->all(),
            'total_dipindah' => $saran->sum('jumlah'),
            'sisa_kurang' => $sisaKurang->sortByDesc('jumlah')->values()->all(),
            'total_sisa_kurang' => $sisaKurang->sum('jumlah'),
        ];
    }
}
