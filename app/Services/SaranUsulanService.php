<?php

namespace App\Services;

use App\Enums\UsulanStatus;
use App\Models\Usulan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Satu rumus saran jumlah usulan per posisi (unit × jabatan), dipakai saat menarik rincian usulan
 * dan saat memeriksa rincian (operator, verifikator BKN, validator KemenPANRB):
 *
 *   saran = kebutuhan − existing + pensiun 5 tahun
 *           − formasi ditetapkan yang belum terisi
 *           − usulan lain yang sedang diproses
 *           − pegawai yang dapat dipindahkan dari unit gemuk (redistribusi, opsional)
 *
 * Kebutuhan memakai ABK final yang berlaku untuk tahun usulan (tahun ABK ≤ tahun usulan), atau
 * kebutuhan sementara bila posisi belum memiliki ABK final. Setiap komponen boleh kosong: posisi
 * tanpa kebutuhan tetap tampil dengan saran 0, dan tidak ada langkah yang diblokir.
 */
class SaranUsulanService
{
    public const STATUS_PROSES = [UsulanStatus::Diajukan, UsulanStatus::VerifikasiBkn, UsulanStatus::PertimbanganTeknis, UsulanStatus::ValidasiKemenpan];

    public function __construct(private MonitoringService $monitoring, private RedistribusiService $redistribusi) {}

    /** @return Collection<string, array> per kunci "unit_kerja_id-jabatan_id" */
    public function hitung(int $instansiId, ?int $tahun = null, bool $denganRedistribusi = true, ?int $kecualiUsulanId = null): Collection
    {
        $filters = array_filter(['instansi_id' => $instansiId, 'tahun_abk' => $tahun]);

        $posisi = $this->monitoring->positionsQuery($filters)
            ->join('unit_kerjas as uk', 'uk.id', '=', 'p.unit_kerja_id')
            ->select('p.*', 'uk.nama as unit_nama')
            ->get()
            ->map(fn ($p) => $this->monitoring->decoratePosition((array) $p))
            ->keyBy(fn ($p) => $p['unit_kerja_id'].'-'.$p['jabatan_id']);

        $proses = DB::table('usulan_details as d')
            ->join('usulans as u', 'u.id', '=', 'd.usulan_id')
            ->where('u.instansi_id', $instansiId)
            ->whereIn('u.status', array_map(fn ($s) => $s->value, self::STATUS_PROSES))
            ->when($kecualiUsulanId, fn ($q, $id) => $q->where('u.id', '!=', $id))
            ->groupBy('d.unit_kerja_id', 'd.jabatan_id')
            ->selectRaw('d.unit_kerja_id, d.jabatan_id, SUM(COALESCE(d.jumlah_rekomendasi, d.jumlah_usul)) as jumlah')
            ->get()->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => (int) $r->jumlah]);

        // pegawai yang disarankan pindah ke posisi ini dari unit gemuk (jabatan sama)
        $masuk = collect($denganRedistribusi ? $this->redistribusi->saran($instansiId, array_filter(['tahun_abk' => $tahun]))['saran'] : [])
            ->groupBy(fn ($s) => $s['ke_unit_id'].'-'.$s['jabatan_id'])
            ->map(fn ($list) => [
                'jumlah' => $list->sum('jumlah'),
                'dari' => $list->map(fn ($s) => "{$s['dari_unit']} ({$s['jumlah']})")->implode(', '),
            ]);

        return $posisi->map(function ($p, $kunci) use ($proses, $masuk) {
            $dalamProses = $proses[$kunci] ?? 0;
            $redistribusi = $masuk[$kunci]['jumlah'] ?? 0;
            $kekurangan = $p['kebutuhan'] - $p['existing'] + $p['pensiun'];

            return [
                'unit_kerja_id' => (int) $p['unit_kerja_id'],
                'unit_nama' => $p['unit_nama'],
                'jabatan_id' => (int) $p['jabatan_id'],
                'jabatan_nama' => $p['jabatan_nama'],
                'sumber' => $p['sumber'],
                'kebutuhan' => $p['kebutuhan'],
                'existing' => $p['existing'],
                'pensiun' => $p['pensiun'],
                'formasi' => $p['formasi'],
                'usulan_proses' => $dalamProses,
                'redistribusi_masuk' => $redistribusi,
                'redistribusi_dari' => $masuk[$kunci]['dari'] ?? null,
                'saran' => max(0, $kekurangan - $p['formasi'] - $dalamProses - $redistribusi),
            ];
        });
    }

    /**
     * Pemeriksaan rincian sebuah usulan terhadap kondisi terkini (tidak memblokir apa pun):
     * saran sistem per rincian, selisih, dan catatan bila usulan melebihi saran. Redistribusi bersifat
     * opsional, sehingga penanda "melebihi" memakai saran tanpa redistribusi; saran bila redistribusi
     * dijalankan ditampilkan sebagai informasi.
     */
    public function periksa(Usulan $usulan): array
    {
        $saran = $this->hitung($usulan->instansi_id, $usulan->tahun, true, $usulan->id);

        $rincian = $usulan->details->mapWithKeys(function ($d) use ($saran) {
            $s = $saran[$d->unit_kerja_id.'-'.$d->jabatan_id] ?? null;
            $nilai = $s ? max(0, $s['kebutuhan'] - $s['existing'] + $s['pensiun'] - $s['formasi'] - $s['usulan_proses']) : 0;
            $usul = (int) ($d->jumlah_rekomendasi ?? $d->jumlah_usul);

            return [$d->id => [
                'sumber' => $s['sumber'] ?? null,
                'kebutuhan' => $s['kebutuhan'] ?? 0,
                'existing' => $s['existing'] ?? 0,
                'pensiun' => $s['pensiun'] ?? 0,
                'formasi' => $s['formasi'] ?? 0,
                'usulan_proses' => $s['usulan_proses'] ?? 0,
                'redistribusi_masuk' => $s['redistribusi_masuk'] ?? 0,
                'redistribusi_dari' => $s['redistribusi_dari'] ?? null,
                'saran' => $nilai,
                'saran_redistribusi' => $s['saran'] ?? 0,
                // posisi tanpa dasar kebutuhan tidak dihitung melebihi, tetapi ditandai tersendiri
                'lebih' => isset($s['sumber']) ? max(0, $usul - $nilai) : 0,
                'catatan' => match (true) {
                    ($s['sumber'] ?? null) === null => 'Belum ada ABK final maupun kebutuhan sementara; angka usulan belum dapat dibandingkan.',
                    $usul > $nilai => 'Melebihi saran sistem sebanyak '.($usul - $nilai).'.',
                    default => null,
                },
            ]];
        });

        return [
            'rincian' => $rincian,
            'jumlah_melebihi' => $rincian->filter(fn ($r) => $r['lebih'] > 0)->count(),
            'total_lebih' => $rincian->sum('lebih'),
            'tanpa_dasar' => $rincian->whereNull('sumber')->count(),
            'kesiapan' => $this->kesiapanAbk($usulan->instansi_id),
        ];
    }

    /**
     * Kesiapan ABK satu instansi: dari posisi yang berpegawai atau berkebutuhan, berapa yang
     * memakai ABK final mutakhir, ABK lama, kebutuhan sementara, atau belum ada dasar sama sekali.
     */
    public function kesiapanAbk(int $instansiId): array
    {
        $minTahun = (int) now()->format('Y') - (int) config('simonkeb.peringatan.abk_maks_umur_tahun');
        $tahunAbk = DB::table('anjab_abks')->where('instansi_id', $instansiId)->where('status', 'final')
            ->groupBy('unit_kerja_id', 'jabatan_id')
            ->selectRaw('unit_kerja_id, jabatan_id, MAX(tahun) as tahun')
            ->get()->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => (int) $r->tahun]);

        $posisi = $this->monitoring->positionsQuery(['instansi_id' => $instansiId])->get();

        $hasil = ['total' => $posisi->count(), 'mutakhir' => 0, 'lama' => 0, 'sementara' => 0, 'belum' => 0];
        foreach ($posisi as $p) {
            $tahun = $tahunAbk[$p->unit_kerja_id.'-'.$p->jabatan_id] ?? null;
            $hasil[match (true) {
                $tahun !== null && $tahun >= $minTahun => 'mutakhir',
                $tahun !== null => 'lama',
                $p->sumber === 'sementara' => 'sementara',
                default => 'belum',
            }]++;
        }
        $hasil['persen'] = $hasil['total'] ? round($hasil['mutakhir'] / $hasil['total'] * 100, 1) : null;
        $hasil['min_tahun'] = $minTahun;

        return $hasil;
    }
}
