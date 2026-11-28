<?php

namespace App\Services;

use App\Models\RekomendasiJf;
use App\Models\SumberData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Perbandingan referensi per instansi × jabatan pokok × jenjang. Setiap sumber aktif milik pembina menjadi satu kolom dan
 * TIDAK saling membatasi: kebutuhan (rekomendasi BKN, penetapan KemenPANRB, ABK) dibandingkan dengan sesamanya, begitu pula
 * bezetting (saat rekomendasi, pegawai SIMONKEB, API Dashboard). Nilai kosong berarti tidak ada data, bukan nol.
 * Asal nilai: fakta/dashboard dibaca dari kebutuhan_fakta; abk/pegawai dihitung langsung dari SIMONKEB lewat kaitan jabatan ke JF.
 */
class PerbandinganJfService
{
    public function __construct(private MonitoringService $monitoring) {}

    /** @return Collection<int, SumberData> sumber aktif pembina menurut urutan */
    public function sumber(int $pembinaId): Collection
    {
        return SumberData::where('pembina_id', $pembinaId)->where('is_active', true)->orderBy('urutan')->orderBy('id')->get();
    }

    /**
     * Baris perbandingan (satu per instansi × jabatan pokok) untuk satu pembina.
     *
     * @return Collection<int, array>
     */
    public function baris(int $pembinaId): Collection
    {
        $sumber = $this->sumber($pembinaId);
        $ambang = (int) config('simonkeb.perbandingan.ambang_beda_persen');

        $header = RekomendasiJf::query()->untukPembina($pembinaId)->with('alias.refInstansi:id,nama,instansi_id', 'jabatanPokok:id,nama,urutan')->get();
        $fakta = $this->faktaPerHeader($header->pluck('id'), $sumber);
        $live = $this->liveTotals($pembinaId);

        return $header->map(function (RekomendasiJf $h) use ($sumber, $fakta, $live, $ambang) {
            $instansiLokal = $h->alias->refInstansi?->instansi_id;
            $nilai = [];
            foreach ($sumber as $s) {
                $nilai[$s->kode] = match ($s->adapter) {
                    'abk' => $instansiLokal ? ($live[$instansiLokal][$h->jabatan_pokok_id]['abk'] ?? null) : null,
                    'pegawai' => $instansiLokal ? ($live[$instansiLokal][$h->jabatan_pokok_id]['pegawai'] ?? null) : null,
                    default => $fakta[$h->id][$s->id] ?? null,
                };
            }

            return [
                'id' => $h->id, 'instansi' => $h->alias->nama, 'jabatan' => $h->jabatanPokok->nama, 'urutan_jabatan' => $h->jabatanPokok->urutan, 'pokja' => $h->pokja,
                'dipetakan' => $h->alias->ref_instansi_id !== null, 'instansi_lokal_id' => $instansiLokal, 'nilai' => $nilai,
                'berjauhan' => $this->kelompokBerjauhan($sumber, $nilai, $ambang),
            ];
        })->sortBy([['instansi', 'asc'], ['urutan_jabatan', 'asc']])->values();
    }

    /** Rincian satu baris: per jenjang untuk tiap sumber, dan per unit kerja untuk sumber yang dihitung langsung dari SIMONKEB. */
    public function rincian(RekomendasiJf $h): array
    {
        $h->loadMissing('alias.refInstansi:id,nama,instansi_id', 'jabatanPokok.jenjangs');
        $pembinaId = $h->jabatanPokok->pembina_id;
        $sumber = $this->sumber($pembinaId);
        $instansiLokal = $h->alias->refInstansi?->instansi_id;
        $jenjangs = $h->jabatanPokok->jenjangs->where('is_active', true)->values();

        $fakta = $h->faktaTerbaru()->whereNotNull('nilai')->get(['sumber_id', 'jenjang_id', 'nilai'])->groupBy('sumber_id');
        $live = $instansiLokal ? $this->liveRincian($instansiLokal, $h->jabatan_pokok_id) : ['jenjang' => [], 'unit' => []];

        $matriks = $jenjangs->map(function ($j) use ($sumber, $fakta, $live) {
            $nilai = [];
            foreach ($sumber as $s) {
                $nilai[$s->kode] = match ($s->adapter) {
                    'abk' => $live['jenjang'][$j->id]['abk'] ?? null,
                    'pegawai' => $live['jenjang'][$j->id]['pegawai'] ?? null,
                    default => ($f = $fakta->get($s->id)?->firstWhere('jenjang_id', $j->id)) ? (int) $f->nilai : null,
                };
            }

            return ['kode' => $j->kode, 'nama' => $j->nama, 'nilai' => $nilai];
        })->all();

        // jabatan SIMONKEB yang terkait pokok ini tetapi tanpa jenjang (tidak dapat dipilah) dihitung terpisah
        return [
            'sumber' => $sumber->map(fn ($s) => ['kode' => $s->kode, 'nama' => $s->nama, 'jenis' => $s->jenis, 'adapter' => $s->adapter, 'sifat' => $s->sifat])->all(),
            'jenjang' => $matriks,
            'unit' => $live['unit'],
            'instansi_lokal' => $instansiLokal,
            'jabatan_terkait' => $instansiLokal ? DB::table('jabatans')->where('jf_jabatan_pokok_id', $h->jabatan_pokok_id)->count() : null,
        ];
    }

    /** Jumlah per header dan sumber (hanya nilai terbaru; sumber fakta dan dashboard). */
    private function faktaPerHeader(Collection $headerIds, Collection $sumber): array
    {
        $idFakta = $sumber->whereIn('adapter', ['fakta', 'dashboard'])->pluck('id');
        if ($headerIds->isEmpty() || $idFakta->isEmpty()) {
            return [];
        }
        $hasil = [];
        foreach ($headerIds->chunk(500) as $potong) {
            foreach (DB::table('kebutuhan_fakta')->where('is_terbaru', true)->whereNotNull('nilai')->whereIn('rekomendasi_jf_id', $potong)->whereIn('sumber_id', $idFakta)
                ->groupBy('rekomendasi_jf_id', 'sumber_id')->selectRaw('rekomendasi_jf_id, sumber_id, SUM(nilai) as total')->get() as $r) {
                $hasil[$r->rekomendasi_jf_id][$r->sumber_id] = (int) $r->total;
            }
        }

        return $hasil;
    }

    /** ABK dan pegawai SIMONKEB per instansi lokal dan jabatan pokok (hanya jabatan yang dikaitkan ke JF milik pembina). */
    private function liveTotals(int $pembinaId): array
    {
        $pokok = DB::table('jf_jabatan_pokok')->where('pembina_id', $pembinaId)->pluck('id');
        $hasil = [];
        foreach ($this->monitoring->positionsQuery([])->join('jabatans as jb', 'jb.id', '=', 'p.jabatan_id')->whereIn('jb.jf_jabatan_pokok_id', $pokok)
            ->selectRaw('p.instansi_id, jb.jf_jabatan_pokok_id as pokok, p.kebutuhan, p.existing, p.sumber')->get() as $r) {
            $x = &$hasil[$r->instansi_id][$r->pokok];
            $x ??= ['abk' => null, 'pegawai' => 0]; // abk null = belum ada ABK final pada jabatan terkait
            if ($r->sumber === 'abk') {
                $x['abk'] = ($x['abk'] ?? 0) + (int) $r->kebutuhan;
            }
            $x['pegawai'] += (int) $r->existing;
            unset($x);
        }

        return $hasil;
    }

    /** Rincian langsung satu instansi lokal dan satu jabatan pokok: per jenjang dan per unit. */
    private function liveRincian(int $instansiLokalId, int $pokokId): array
    {
        $jenjang = [];
        $unit = [];
        foreach ($this->monitoring->positionsQuery(['instansi_id' => $instansiLokalId])->join('jabatans as jb', 'jb.id', '=', 'p.jabatan_id')
            ->join('unit_kerjas as uk', 'uk.id', '=', 'p.unit_kerja_id')->where('jb.jf_jabatan_pokok_id', $pokokId)
            ->selectRaw('p.unit_kerja_id, uk.nama as unit_nama, jb.nama as jabatan_nama, jb.jf_jenjang_id, p.kebutuhan, p.existing, p.sumber')
            ->orderBy('uk.nama')->get() as $r) {
            $abk = $r->sumber === 'abk' ? (int) $r->kebutuhan : null;
            $peg = (int) $r->existing;
            $j = &$jenjang[$r->jf_jenjang_id];
            $j['abk'] = $abk === null ? ($j['abk'] ?? null) : ($j['abk'] ?? 0) + $abk;
            $j['pegawai'] = ($j['pegawai'] ?? 0) + $peg;
            unset($j);
            $unit[] = ['unit' => $r->unit_nama, 'jabatan' => $r->jabatan_nama, 'abk' => $abk, 'pegawai' => $peg, 'unit_id' => $r->unit_kerja_id];
        }

        return ['jenjang' => $jenjang, 'unit' => $unit];
    }

    /**
     * Tandai kelompok sumber yang berjauhan: bila dalam satu jenis (kebutuhan atau bezetting) ada minimal dua nilai dan selisih
     * terbesar melampaui ambang persen dari nilai terbesar. @return array{kebutuhan: bool, bezetting: bool}
     */
    private function kelompokBerjauhan(Collection $sumber, array $nilai, int $ambang): array
    {
        $hasil = [];
        foreach (['kebutuhan', 'bezetting'] as $jenis) {
            $v = $sumber->where('jenis', $jenis)->map(fn ($s) => $nilai[$s->kode] ?? null)->filter(fn ($x) => $x !== null)->values();
            $hasil[$jenis] = $v->count() >= 2 && $v->max() > 0 && (($v->max() - $v->min()) / $v->max() * 100) > $ambang;
        }

        return $hasil;
    }
}
