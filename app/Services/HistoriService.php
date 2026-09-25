<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Histori perubahan data existing:
 *  - tren bulanan kebutuhan vs existing (dari rekam jejak bulanan)
 *  - pergerakan pegawai (masuk, keluar, mutasi, ganti jabatan) dari riwayat pegawai
 *  - identifikasi instansi / unit / posisi yang tidak bergerak sama sekali dalam N bulan
 */
class HistoriService
{
    public const LEVEL = ['instansi', 'unit', 'posisi'];

    public function __construct(private MonitoringService $monitoring) {}

    /** Tren bulanan; bulan berjalan dihitung langsung (live). */
    public function tren(array $filters, int $bulan = 12): array
    {
        $mulai = now()->startOfMonth()->subMonths($bulan - 1);

        $rows = DB::table('bezetting_snapshots')
            ->where('periode', '>=', $mulai->toDateString())
            ->where('periode', '<', now()->startOfMonth()->toDateString())
            ->when($filters['instansi_id'] ?? null, fn ($q, $i) => $q->where('instansi_id', $i))
            ->when($filters['unit_ids'] ?? null, fn ($q, $ids) => $q->whereIn('unit_kerja_id', $ids))
            ->groupBy('periode')
            ->selectRaw('periode, SUM(kebutuhan) as kebutuhan, SUM(existing) as existing, SUM(pensiun) as pensiun,
                SUM(CASE WHEN kebutuhan > existing THEN kebutuhan - existing ELSE 0 END) as kurang,
                SUM(CASE WHEN existing > kebutuhan THEN existing - kebutuhan ELSE 0 END) as lebih')
            ->get()
            ->keyBy(fn ($r) => substr((string) $r->periode, 0, 7));

        $live = $this->monitoring->summary($filters);
        $hasil = [];

        for ($i = 0; $i < $bulan; $i++) {
            $p = $mulai->copy()->addMonths($i);
            $key = $p->format('Y-m');
            $row = $key === now()->format('Y-m') ? (object) $live : $rows->get($key);

            $hasil[] = [
                'periode' => $key,
                'label' => $p->translatedFormat('M Y'),
                'ada_data' => (bool) $row,
                'kebutuhan' => $row ? (int) $row->kebutuhan : null,
                'existing' => $row ? (int) $row->existing : null,
                'kurang' => $row ? (int) $row->kurang : null,
                'lebih' => $row ? (int) $row->lebih : null,
            ];
        }

        return $hasil;
    }

    /** Jumlah pergerakan per bulan per jenis (masuk, keluar, mutasi_unit, ganti_jabatan). */
    public function pergerakanBulanan(array $filters, int $bulan = 12): array
    {
        $mulai = now()->startOfMonth()->subMonths($bulan - 1);

        $rows = $this->riwayatQuery($filters)
            ->where('r.created_at', '>=', $mulai)
            ->get(['r.jenis', 'r.created_at'])
            ->groupBy(fn ($r) => substr((string) $r->created_at, 0, 7));

        $hasil = [];
        for ($i = 0; $i < $bulan; $i++) {
            $key = $mulai->copy()->addMonths($i)->format('Y-m');
            $list = $rows->get($key, collect());
            $hasil[] = ['periode' => $key, 'label' => $mulai->copy()->addMonths($i)->translatedFormat('M Y')]
                + collect(['masuk', 'keluar', 'mutasi_unit', 'ganti_jabatan'])->mapWithKeys(fn ($j) => [$j => $list->where('jenis', $j)->count()])->all();
        }

        return $hasil;
    }

    /**
     * Objek (instansi / unit / posisi) beserta jumlah pergerakan dalam N bulan terakhir,
     * pergerakan terakhir, existing awal periode (rekam jejak) dan existing sekarang.
     * Opsi hanya_diam = hanya yang tidak bergerak sama sekali.
     */
    public function analisis(string $level, array $filters, int $bulan = 6, bool $hanyaDiam = true): Collection
    {
        $sejak = now()->subMonths($bulan);
        $periodeAwal = now()->startOfMonth()->subMonths($bulan)->toDateString();

        // posisi terdampak setiap peristiwa: posisi asal dan posisi tujuan
        $dampak = $this->riwayatQuery($filters, 'dari')->selectRaw('r.dari_unit_id as unit_kerja_id, r.dari_jabatan_id as jabatan_id, r.created_at')
            ->whereNotNull('r.dari_unit_id')
            ->unionAll($this->riwayatQuery($filters, 'ke')->selectRaw('r.ke_unit_id as unit_kerja_id, r.ke_jabatan_id as jabatan_id, r.created_at')->whereNotNull('r.ke_unit_id'));

        $kunci = match ($level) {
            'instansi' => ['uk.instansi_id'],
            'unit' => ['d.unit_kerja_id'],
            default => ['d.unit_kerja_id', 'd.jabatan_id'],
        };
        $gerak = DB::query()->fromSub($dampak, 'd')
            ->join('unit_kerjas as uk', 'uk.id', '=', 'd.unit_kerja_id')
            ->groupBy(...$kunci)
            ->selectRaw(implode(', ', $kunci).', MAX(d.created_at) as terakhir, SUM(CASE WHEN d.created_at >= ? THEN 1 ELSE 0 END) as jumlah', [$sejak])
            ->get()
            ->keyBy(fn ($r) => $this->key($level, $r));

        // kondisi sekarang (live)
        $posisi = $this->monitoring->positionsQuery($filters)->get();
        $sekarang = match ($level) {
            'instansi' => $posisi->groupBy('instansi_id'),
            'unit' => $posisi->groupBy('unit_kerja_id'),
            default => $posisi->groupBy(fn ($p) => $p->unit_kerja_id.'-'.$p->jabatan_id),
        };

        // existing pada awal periode (rekam jejak bulan tersebut)
        $awalQuery = DB::table('bezetting_snapshots as s')->where('s.periode', $periodeAwal)
            ->when($filters['instansi_id'] ?? null, fn ($q, $i) => $q->where('s.instansi_id', $i))
            ->when($filters['unit_ids'] ?? null, fn ($q, $ids) => $q->whereIn('s.unit_kerja_id', $ids));
        $awal = match ($level) {
            'instansi' => $awalQuery->groupBy('s.instansi_id')->selectRaw('s.instansi_id as k, SUM(s.existing) as existing')->pluck('existing', 'k'),
            'unit' => $awalQuery->groupBy('s.unit_kerja_id')->selectRaw('s.unit_kerja_id as k, SUM(s.existing) as existing')->pluck('existing', 'k'),
            default => $awalQuery->get(['s.unit_kerja_id', 's.jabatan_id', 's.existing'])->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => $r->existing]),
        };

        $nama = $this->nama($level, $sekarang->keys());

        return $sekarang->map(function ($rows, $key) use ($gerak, $awal, $nama, $level) {
            $g = $gerak->get($key);
            $kebutuhan = (int) $rows->sum('kebutuhan');
            $existing = (int) $rows->sum('existing');
            $awalExisting = $awal->has($key) ? (int) $awal[$key] : null;
            $first = $rows->first();

            return [
                'key' => (string) $key,
                'level' => $level,
                'instansi_id' => $first->instansi_id,
                'unit_kerja_id' => $level === 'instansi' ? null : $first->unit_kerja_id,
                'jabatan_id' => $level === 'posisi' ? $first->jabatan_id : null,
                'nama' => $nama[$key]['nama'] ?? '-',
                'induk' => $nama[$key]['induk'] ?? null,
                'kebutuhan' => $kebutuhan,
                'existing' => $existing,
                'existing_awal' => $awalExisting,
                'perubahan' => $awalExisting === null ? null : $existing - $awalExisting,
                'selisih' => $existing - $kebutuhan,
                'pergerakan' => (int) ($g->jumlah ?? 0),
                'terakhir_bergerak' => $g->terakhir ?? null,
                'kondisi' => match (true) {
                    $kebutuhan === 0 && $existing > 0 => 'tanpa_abk',
                    $existing === 0 && $kebutuhan > 0 => 'kosong',
                    $existing < $kebutuhan => 'kurang',
                    $existing > $kebutuhan => 'lebih',
                    default => 'sesuai',
                },
            ];
        })
            ->when($hanyaDiam, fn ($c) => $c->filter(fn ($r) => $r['pergerakan'] === 0))
            // prioritaskan yang diam tetapi bermasalah (kosong/kurang/lebih)
            ->sortBy([
                fn ($a, $b) => ($a['kondisi'] === 'sesuai') <=> ($b['kondisi'] === 'sesuai'),
                fn ($a, $b) => abs($b['selisih']) <=> abs($a['selisih']),
            ])
            ->values();
    }

    /** Log peristiwa pegawai (paginasi). */
    public function log(array $filters, ?string $jenis = null, ?Carbon $sejak = null)
    {
        return $this->riwayatQuery($filters)
            ->join('pegawais as p', 'p.id', '=', 'r.pegawai_id')
            ->leftJoin('unit_kerjas as du', 'du.id', '=', 'r.dari_unit_id')
            ->leftJoin('unit_kerjas as ku', 'ku.id', '=', 'r.ke_unit_id')
            ->leftJoin('jabatans as dj', 'dj.id', '=', 'r.dari_jabatan_id')
            ->leftJoin('jabatans as kj', 'kj.id', '=', 'r.ke_jabatan_id')
            ->leftJoin('users as us', 'us.id', '=', 'r.user_id')
            ->when($jenis, fn ($q) => $q->where('r.jenis', $jenis))
            ->when($sejak, fn ($q) => $q->where('r.created_at', '>=', $sejak))
            ->select('r.id', 'r.jenis', 'r.created_at', 'r.keterangan', 'r.sumber', 'p.id as pegawai_id', 'p.nip', 'p.nama',
                'du.nama as dari_unit', 'ku.nama as ke_unit', 'dj.nama as dari_jabatan', 'kj.nama as ke_jabatan', 'us.name as oleh')
            ->orderByDesc('r.created_at')->orderByDesc('r.id');
    }

    /** Riwayat pegawai dibatasi instansi/unit (lewat unit asal atau tujuan). */
    private function riwayatQuery(array $filters, string $sisi = 'semua'): Builder
    {
        $q = DB::table('pegawai_riwayats as r');
        $unitIds = $filters['unit_ids'] ?? null;
        $instansiId = $filters['instansi_id'] ?? null;

        if ($unitIds) {
            match ($sisi) {
                'dari' => $q->whereIn('r.dari_unit_id', $unitIds),
                'ke' => $q->whereIn('r.ke_unit_id', $unitIds),
                default => $q->where(fn ($w) => $w->whereIn('r.dari_unit_id', $unitIds)->orWhereIn('r.ke_unit_id', $unitIds)),
            };
        } elseif ($instansiId) {
            $units = DB::table('unit_kerjas')->where('instansi_id', $instansiId)->select('id');
            match ($sisi) {
                'dari' => $q->whereIn('r.dari_unit_id', $units),
                'ke' => $q->whereIn('r.ke_unit_id', $units),
                default => $q->where(fn ($w) => $w->whereIn('r.dari_unit_id', $units)->orWhereIn('r.ke_unit_id', $units)),
            };
        }

        return $q;
    }

    private function key(string $level, $row): string
    {
        return match ($level) {
            'instansi' => (string) $row->instansi_id,
            'unit' => (string) $row->unit_kerja_id,
            default => $row->unit_kerja_id.'-'.$row->jabatan_id,
        };
    }

    private function nama(string $level, Collection $keys): array
    {
        if ($level === 'instansi') {
            return DB::table('instansis')->whereIn('id', $keys)->pluck('nama', 'id')->map(fn ($n) => ['nama' => $n])->all();
        }

        $unitIds = $keys->map(fn ($k) => (int) explode('-', (string) $k)[0])->unique();
        $units = DB::table('unit_kerjas as u')->join('instansis as i', 'i.id', '=', 'u.instansi_id')
            ->whereIn('u.id', $unitIds)->get(['u.id', 'u.nama', 'i.nama as instansi'])->keyBy('id');

        if ($level === 'unit') {
            return $keys->mapWithKeys(fn ($k) => [$k => ['nama' => $units[$k]->nama ?? '-', 'induk' => $units[$k]->instansi ?? null]])->all();
        }

        $jabatans = DB::table('jabatans')->whereIn('id', $keys->map(fn ($k) => (int) explode('-', (string) $k)[1])->unique())->pluck('nama', 'id');

        return $keys->mapWithKeys(function ($k) use ($units, $jabatans) {
            [$u, $j] = array_map('intval', explode('-', (string) $k));

            return [$k => ['nama' => $jabatans[$j] ?? '-', 'induk' => ($units[$u]->nama ?? '-').' · '.($units[$u]->instansi ?? '')]];
        })->all();
    }
}
