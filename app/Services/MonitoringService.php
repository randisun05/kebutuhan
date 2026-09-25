<?php

namespace App\Services;

use App\Enums\UsulanStatus;
use App\Models\UnitKerja;
use App\Support\Referensi;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung perbandingan kebutuhan (hasil ABK final) dengan pegawai existing
 * (bezetting) langsung dari database, sehingga setiap perubahan data pegawai,
 * ABK maupun penetapan langsung tercermin di dashboard monitoring.
 *
 * Satuan terkecil perhitungan adalah "posisi" = kombinasi unit kerja + jabatan.
 * Kekurangan dan kelebihan dihitung per posisi terlebih dahulu kemudian dijumlahkan,
 * sehingga kelebihan di satu unit tidak menutupi kekurangan di unit lain.
 *
 * Filter yang didukung: instansi_id, unit_ids (array), jenis (jenis jabatan),
 * jabatan_id, status (kurang|lebih|sesuai|kosong|tanpa_abk), q (nama jabatan).
 */
class MonitoringService
{
    /** Query posisi: satu baris per unit kerja + jabatan. */
    public function positionsQuery(array $filters = []): Builder
    {
        $horizon = now()->addYears(Referensi::HORIZON_PROYEKSI_TAHUN)->toDateString();

        // ABK final terbaru per posisi (opsional dibatasi s.d. tahun tertentu)
        $tahunAbk = $filters['tahun_abk'] ?? null;
        $abk = DB::table('anjab_abks as a')
            ->where('a.status', 'final')
            ->whereRaw('a.tahun = (SELECT MAX(b.tahun) FROM anjab_abks b WHERE b.unit_kerja_id = a.unit_kerja_id
                AND b.jabatan_id = a.jabatan_id AND b.status = ?'.($tahunAbk ? ' AND b.tahun <= ?' : '').')',
                $tahunAbk ? ['final', (int) $tahunAbk] : ['final'])
            ->selectRaw('a.instansi_id as instansi_id, a.unit_kerja_id as unit_kerja_id, a.jabatan_id as jabatan_id, a.kebutuhan as k, 0 as e, 0 as p, 0 as f');

        $pegawai = DB::table('pegawais')
            ->where('is_active', true)
            ->groupBy('instansi_id', 'unit_kerja_id', 'jabatan_id')
            ->selectRaw('instansi_id, unit_kerja_id, jabatan_id, 0 as k, COUNT(*) as e, SUM(CASE WHEN tmt_pensiun IS NOT NULL AND tmt_pensiun <= ? THEN 1 ELSE 0 END) as p, 0 as f', [$horizon]);

        $formasi = DB::table('usulan_details as d')
            ->join('usulans as u', 'u.id', '=', 'd.usulan_id')
            ->where('u.status', UsulanStatus::Ditetapkan->value)
            ->groupBy('u.instansi_id', 'd.unit_kerja_id', 'd.jabatan_id')
            // formasi yang sudah ditetapkan tetapi belum terisi (hasil seleksi belum diangkat)
            ->selectRaw('u.instansi_id as instansi_id, d.unit_kerja_id as unit_kerja_id, d.jabatan_id as jabatan_id, 0 as k, 0 as e, 0 as p,
                SUM(CASE WHEN COALESCE(d.jumlah_ditetapkan, 0) > d.jumlah_terisi THEN COALESCE(d.jumlah_ditetapkan, 0) - d.jumlah_terisi ELSE 0 END) as f');

        $this->applyScope($abk, $filters, 'a.');
        $this->applyScope($pegawai, $filters, '');
        $this->applyScope($formasi, $filters, 'u.', 'd.');

        $union = $abk->unionAll($pegawai)->unionAll($formasi);

        $positions = DB::query()->fromSub($union, 'x')
            ->join('jabatans as j', 'j.id', '=', 'x.jabatan_id')
            ->groupBy('x.instansi_id', 'x.unit_kerja_id', 'x.jabatan_id', 'j.jenis', 'j.nama')
            ->selectRaw('x.instansi_id, x.unit_kerja_id, x.jabatan_id, j.jenis, j.nama as jabatan_nama,
                SUM(x.k) as kebutuhan, SUM(x.e) as existing, SUM(x.p) as pensiun, SUM(x.f) as formasi');

        if (! empty($filters['jenis'])) {
            $positions->where('j.jenis', $filters['jenis']);
        }
        if (! empty($filters['jabatan_id'])) {
            $positions->where('x.jabatan_id', $filters['jabatan_id']);
        }
        if (! empty($filters['q'])) {
            $positions->where('j.nama', 'like', '%'.$filters['q'].'%');
        }

        $query = DB::query()->fromSub($positions, 'p');

        match ($filters['status'] ?? null) {
            'kurang' => $query->whereColumn('p.existing', '<', 'p.kebutuhan'),
            'lebih' => $query->whereColumn('p.existing', '>', 'p.kebutuhan'),
            'sesuai' => $query->whereColumn('p.existing', '=', 'p.kebutuhan'),
            'kosong' => $query->where('p.existing', 0)->where('p.kebutuhan', '>', 0),
            'tanpa_abk' => $query->where('p.kebutuhan', 0)->where('p.existing', '>', 0),
            default => null,
        };

        return $query;
    }

    private function applyScope(Builder $query, array $filters, string $instansiPrefix, ?string $unitPrefix = null): void
    {
        $unitPrefix ??= $instansiPrefix;

        if (! empty($filters['instansi_id'])) {
            $query->where($instansiPrefix.'instansi_id', $filters['instansi_id']);
        }
        if (! empty($filters['unit_ids'])) {
            $query->whereIn($unitPrefix.'unit_kerja_id', $filters['unit_ids']);
        }
    }

    /** Ekspresi agregat yang dipakai di semua tingkat rekap. */
    private function aggregateSelect(): string
    {
        return 'COUNT(*) as jumlah_jabatan,
            COALESCE(SUM(p.kebutuhan), 0) as kebutuhan,
            COALESCE(SUM(p.existing), 0) as existing,
            COALESCE(SUM(CASE WHEN p.kebutuhan > p.existing THEN p.kebutuhan - p.existing ELSE 0 END), 0) as kurang,
            COALESCE(SUM(CASE WHEN p.existing > p.kebutuhan THEN p.existing - p.kebutuhan ELSE 0 END), 0) as lebih,
            COALESCE(SUM(CASE WHEN p.kebutuhan > p.existing THEN 1 ELSE 0 END), 0) as jabatan_kurang,
            COALESCE(SUM(CASE WHEN p.existing > p.kebutuhan THEN 1 ELSE 0 END), 0) as jabatan_lebih,
            COALESCE(SUM(CASE WHEN p.existing = p.kebutuhan THEN 1 ELSE 0 END), 0) as jabatan_sesuai,
            COALESCE(SUM(CASE WHEN p.existing = 0 AND p.kebutuhan > 0 THEN 1 ELSE 0 END), 0) as jabatan_kosong,
            COALESCE(SUM(CASE WHEN p.kebutuhan = 0 AND p.existing > 0 THEN 1 ELSE 0 END), 0) as tanpa_abk,
            COALESCE(SUM(p.pensiun), 0) as pensiun,
            COALESCE(SUM(p.formasi), 0) as formasi';
    }

    public function summary(array $filters = []): array
    {
        $row = $this->positionsQuery($filters)->selectRaw($this->aggregateSelect())->first();

        return $this->decorate((array) $row);
    }

    public function byInstansi(array $filters = []): Collection
    {
        $rows = $this->positionsQuery($filters)
            ->groupBy('p.instansi_id')
            ->selectRaw('p.instansi_id, '.$this->aggregateSelect())
            ->get()->keyBy('instansi_id');

        $instansis = DB::table('instansis')
            ->where('is_active', true)
            ->when(! empty($filters['instansi_id']), fn ($q) => $q->where('id', $filters['instansi_id']))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'jenis']);

        return $instansis->map(function ($instansi) use ($rows) {
            $agg = $rows->get($instansi->id);

            return array_merge(
                ['id' => $instansi->id, 'kode' => $instansi->kode, 'nama' => $instansi->nama, 'jenis' => $instansi->jenis],
                $this->decorate($agg ? (array) $agg : [])
            );
        })->values();
    }

    /** Rekap per jenis jabatan (JPT, administrator, pengawas, pelaksana, fungsional). */
    public function byJenisJabatan(array $filters = []): Collection
    {
        $rows = $this->positionsQuery($filters)
            ->groupBy('p.jenis')
            ->selectRaw('p.jenis, '.$this->aggregateSelect())
            ->get()->keyBy('jenis');

        return collect(Referensi::JENIS_JABATAN)->map(fn ($label, $jenis) => array_merge(
            ['jenis' => $jenis, 'label' => $label],
            $this->decorate($rows->has($jenis) ? (array) $rows->get($jenis) : [])
        ))->values();
    }

    /**
     * Rekap per unit kerja satu instansi. Setiap unit memuat angka unit itu
     * sendiri ("own") dan akumulasi termasuk seluruh sub-unit ("total").
     */
    public function byUnit(int $instansiId, array $filters = []): Collection
    {
        $filters['instansi_id'] = $instansiId;

        $rows = $this->positionsQuery($filters)
            ->groupBy('p.unit_kerja_id')
            ->selectRaw('p.unit_kerja_id, '.$this->aggregateSelect())
            ->get()->keyBy('unit_kerja_id');

        $tree = UnitKerja::flatTree($instansiId);
        $metricKeys = ['jumlah_jabatan', 'kebutuhan', 'existing', 'kurang', 'lebih', 'jabatan_kurang', 'jabatan_lebih',
            'jabatan_sesuai', 'jabatan_kosong', 'tanpa_abk', 'pensiun', 'formasi'];

        $own = $tree->mapWithKeys(fn ($u) => [$u['id'] => $this->decorate($rows->has($u['id']) ? (array) $rows->get($u['id']) : [])]);

        // akumulasi dari bawah ke atas (sub-unit ke induk)
        $totals = $own->map(fn ($m) => array_intersect_key($m, array_flip($metricKeys)))->all();
        $parents = $tree->pluck('parent_id', 'id');
        foreach ($tree->sortByDesc('depth') as $unit) {
            $parentId = $parents[$unit['id']] ?? null;
            if ($parentId && isset($totals[$parentId])) {
                foreach ($metricKeys as $key) {
                    $totals[$parentId][$key] += $totals[$unit['id']][$key];
                }
            }
        }

        return $tree->map(fn ($unit) => array_merge($unit, [
            'own' => $own[$unit['id']],
            'total' => $this->decorate($totals[$unit['id']]),
            'has_children' => $tree->contains('parent_id', $unit['id']),
        ]))->values();
    }

    /** Daftar posisi (jabatan per unit) lengkap dengan nama unit & instansi. */
    public function positions(array $filters = [], int $limit = 500): Collection
    {
        return $this->positionsQuery($filters)
            ->join('unit_kerjas as uk', 'uk.id', '=', 'p.unit_kerja_id')
            ->join('instansis as i', 'i.id', '=', 'p.instansi_id')
            ->select('p.*', 'uk.nama as unit_nama', 'i.nama as instansi_nama')
            ->orderBy('i.nama')->orderBy('uk.nama')->orderBy('p.jabatan_nama')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->decoratePosition((array) $row));
    }

    public function decoratePosition(array $row): array
    {
        $kebutuhan = (int) $row['kebutuhan'];
        $existing = (int) $row['existing'];
        $selisih = $existing - $kebutuhan;

        return array_merge($row, [
            'kebutuhan' => $kebutuhan,
            'existing' => $existing,
            'pensiun' => (int) $row['pensiun'],
            'formasi' => (int) $row['formasi'],
            'selisih' => $selisih,
            'jenis_label' => Referensi::JENIS_JABATAN[$row['jenis']] ?? $row['jenis'],
            'status' => match (true) {
                $kebutuhan === 0 && $existing > 0 => 'tanpa_abk',
                $existing === 0 && $kebutuhan > 0 => 'kosong',
                $selisih < 0 => 'kurang',
                $selisih > 0 => 'lebih',
                default => 'sesuai',
            },
            'persentase' => $kebutuhan > 0 ? round($existing / $kebutuhan * 100, 1) : null,
        ]);
    }

    /** Normalisasi angka + indikator turunan (persentase pemenuhan, status). */
    private function decorate(array $agg): array
    {
        $keys = ['jumlah_jabatan', 'kebutuhan', 'existing', 'kurang', 'lebih', 'jabatan_kurang', 'jabatan_lebih',
            'jabatan_sesuai', 'jabatan_kosong', 'tanpa_abk', 'pensiun', 'formasi'];

        $data = [];
        foreach ($keys as $key) {
            $data[$key] = (int) ($agg[$key] ?? 0);
        }

        $data['selisih'] = $data['existing'] - $data['kebutuhan'];
        $data['persentase'] = $data['kebutuhan'] > 0 ? round($data['existing'] / $data['kebutuhan'] * 100, 1) : null;
        $data['kondisi'] = match (true) {
            $data['kebutuhan'] === 0 && $data['existing'] === 0 => 'belum_ada_data',
            $data['persentase'] === null => 'tanpa_abk',
            $data['persentase'] < 90 => 'kurang',
            $data['persentase'] > 110 => 'lebih',
            default => 'ideal',
        };

        return $data;
    }
}
