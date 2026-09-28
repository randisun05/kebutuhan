<?php

namespace App\Services;

use App\Enums\UsulanStatus;
use App\Models\Peringatan;
use App\Models\UnitKerja;
use App\Support\Referensi;
use Illuminate\Support\Facades\DB;

/**
 * Pusat laporan. Setiap laporan menghasilkan struktur tabel yang sama
 * (judul, subjudul, kolom, baris, total) sehingga bisa ditampilkan, diekspor ke Excel, maupun PDF.
 */
class LaporanService
{
    public const JENIS = [
        'rekap_instansi' => 'Rekap kebutuhan vs existing per instansi',
        'rekap_unit' => 'Rekap kebutuhan vs existing per unit kerja',
        'rekap_jabatan' => 'Rekap kebutuhan per jabatan',
        'posisi_bermasalah' => 'Daftar jabatan kosong / kurang / gemuk',
        'usulan_penetapan' => 'Rekap usulan, penetapan, dan pengisian formasi',
        'pensiun' => 'Proyeksi pegawai pensiun',
        'abk' => 'Rekap ABK dan efektivitas jabatan',
        'tidak_bergerak' => 'Unit tanpa pergerakan data existing',
        'peringatan' => 'Peringatan dini terbuka',
    ];

    public function __construct(
        private MonitoringService $monitoring,
        private HistoriService $histori,
    ) {}

    /**
     * @return array{judul:string, subjudul:string, kolom:array, baris:array, total:?array, angka:array}
     */
    public function buat(string $jenis, array $p): array
    {
        $filters = array_filter([
            'instansi_id' => $p['instansi_id'] ?? null,
            'jenis' => $p['jenis_jabatan'] ?? null,
            'unit_ids' => ! empty($p['unit_kerja_id']) ? UnitKerja::descendantIds((int) $p['unit_kerja_id']) : null,
        ]);
        $cakupan = ! empty($p['instansi_id']) ? DB::table('instansis')->where('id', $p['instansi_id'])->value('nama') : 'Semua instansi';
        $sub = $cakupan.' · per '.now()->translatedFormat('d F Y H:i');

        $hasil = match ($jenis) {
            'rekap_instansi' => $this->rekap(
                $this->monitoring->byInstansi($filters)->map(fn ($r) => ['nama' => $r['nama']] + $r), 'Instansi'),
            'rekap_unit' => $this->rekap(
                empty($p['instansi_id']) ? collect() : $this->monitoring->byUnit((int) $p['instansi_id'], $filters)
                    ->map(fn ($u) => ['nama' => str_repeat('— ', $u['depth']).$u['nama']] + $u['own']), 'Unit kerja'),
            'rekap_jabatan' => $this->rekap(
                $this->monitoring->byJabatan($filters)->map(fn ($r) => ['nama' => $r['jabatan_nama'].' ('.$r['jenis_label'].')'] + $r), 'Jabatan'),
            'posisi_bermasalah' => $this->posisiBermasalah($filters),
            'usulan_penetapan' => $this->usulan($p),
            'pensiun' => $this->pensiun($filters, (int) ($p['tahun'] ?? Referensi::HORIZON_PROYEKSI_TAHUN)),
            'abk' => $this->abk($p),
            'tidak_bergerak' => $this->tidakBergerak($filters, (int) ($p['bulan'] ?? 6)),
            'peringatan' => $this->peringatan($p),
        };

        return ['judul' => self::JENIS[$jenis], 'subjudul' => $sub] + $hasil;
    }

    private function rekap($rows, string $label): array
    {
        $kolom = [$label, 'Posisi', 'Kebutuhan', 'Existing', 'Selisih', 'Kurang', 'Lebih', 'Kosong', 'Pensiun ≤5 th', 'Formasi blm terisi', '% Pemenuhan'];
        $baris = $rows->map(fn ($r) => [$r['nama'], $r['jumlah_jabatan'], $r['kebutuhan'], $r['existing'], $r['selisih'], $r['kurang'], $r['lebih'],
            $r['jabatan_kosong'], $r['pensiun'], $r['formasi'], $r['persentase']])->values()->all();

        $sum = fn ($i) => array_sum(array_column($baris, $i));
        $keb = $sum(2);

        return [
            'kolom' => $kolom,
            'baris' => $baris,
            'total' => ['Jumlah', $sum(1), $keb, $sum(3), $sum(3) - $keb, $sum(5), $sum(6), $sum(7), $sum(8), $sum(9), $keb ? round($sum(3) / $keb * 100, 1) : null],
            'angka' => ['kebutuhan' => $keb, 'existing' => $sum(3), 'kurang' => $sum(5), 'lebih' => $sum(6)],
        ];
    }

    private function posisiBermasalah(array $filters): array
    {
        $rows = $this->monitoring->positions($filters, 100000)->whereIn('status', ['kosong', 'kurang', 'lebih', 'tanpa_abk']);
        $label = ['kosong' => 'Kosong', 'kurang' => 'Kurang', 'lebih' => 'Lebih (gemuk)', 'tanpa_abk' => 'Belum ada ABK'];

        return [
            'kolom' => ['Instansi', 'Unit kerja', 'Jabatan', 'Kebutuhan', 'Existing', 'Selisih', 'Pensiun ≤5 th', 'Kondisi'],
            'baris' => $rows->sortBy('selisih')->map(fn ($r) => [$r['instansi_nama'], $r['unit_nama'], $r['jabatan_nama'], $r['kebutuhan'],
                $r['existing'], $r['selisih'], $r['pensiun'], $label[$r['status']]])->values()->all(),
            'total' => null,
            'angka' => collect($label)->mapWithKeys(fn ($l, $k) => [$k => $rows->where('status', $k)->count()])->all(),
        ];
    }

    private function usulan(array $p): array
    {
        $rows = DB::table('usulans as u')
            ->join('instansis as i', 'i.id', '=', 'u.instansi_id')
            ->leftJoin('usulan_details as d', 'd.usulan_id', '=', 'u.id')
            ->leftJoin('penetapans as pt', 'pt.usulan_id', '=', 'u.id')
            ->when($p['instansi_id'] ?? null, fn ($q, $i) => $q->where('u.instansi_id', $i))
            ->when($p['tahun'] ?? null, fn ($q, $t) => $q->where('u.tahun', $t))
            ->groupBy('u.id', 'u.nomor', 'i.nama', 'u.tahun', 'u.jenis_asn', 'u.status', 'pt.nomor_sk')
            ->selectRaw('u.nomor, i.nama, u.tahun, u.jenis_asn, u.status, pt.nomor_sk,
                COALESCE(SUM(d.jumlah_usul),0) as usul, SUM(d.jumlah_rekomendasi) as rekomendasi,
                SUM(d.jumlah_ditetapkan) as ditetapkan, COALESCE(SUM(d.jumlah_terisi),0) as terisi')
            ->orderBy('i.nama')->orderByDesc('u.tahun')
            ->get();

        $baris = $rows->map(fn ($r) => [$r->nomor, $r->nama, $r->tahun, strtoupper($r->jenis_asn), UsulanStatus::from($r->status)->label(),
            (int) $r->usul, $r->rekomendasi, $r->ditetapkan, (int) $r->terisi, $r->nomor_sk])->all();

        return [
            'kolom' => ['Nomor', 'Instansi', 'Tahun', 'ASN', 'Status', 'Diusulkan', 'Rekomendasi BKN', 'Ditetapkan', 'Terisi', 'Nomor SK'],
            'baris' => $baris,
            'total' => ['Jumlah', '', '', '', '', array_sum(array_column($baris, 5)), array_sum(array_column($baris, 6)),
                array_sum(array_column($baris, 7)), array_sum(array_column($baris, 8)), ''],
            'angka' => ['usulan' => count($baris), 'diusulkan' => array_sum(array_column($baris, 5)), 'ditetapkan' => array_sum(array_column($baris, 7))],
        ];
    }

    private function pensiun(array $filters, int $tahun): array
    {
        $rows = DB::table('pegawais as pg')
            ->join('instansis as i', 'i.id', '=', 'pg.instansi_id')
            ->join('unit_kerjas as uk', 'uk.id', '=', 'pg.unit_kerja_id')
            ->join('jabatans as j', 'j.id', '=', 'pg.jabatan_id')
            ->where('pg.is_active', true)->whereNotNull('pg.tmt_pensiun')
            ->where('pg.tmt_pensiun', '<=', now()->addYears($tahun)->toDateString())
            ->when($filters['instansi_id'] ?? null, fn ($q, $x) => $q->where('pg.instansi_id', $x))
            ->when($filters['unit_ids'] ?? null, fn ($q, $x) => $q->whereIn('pg.unit_kerja_id', $x))
            ->when($filters['jenis'] ?? null, fn ($q, $x) => $q->where('j.jenis', $x))
            ->orderBy('pg.tmt_pensiun')
            ->get(['pg.nip', 'pg.nama', 'i.nama as instansi', 'uk.nama as unit', 'j.nama as jabatan', 'pg.golongan', 'pg.tanggal_lahir', 'pg.tmt_pensiun']);

        return [
            'kolom' => ['NIP', 'Nama', 'Instansi', 'Unit kerja', 'Jabatan', 'Gol.', 'Tgl lahir', 'TMT pensiun'],
            'baris' => $rows->map(fn ($r) => [$r->nip, $r->nama, $r->instansi, $r->unit, $r->jabatan, $r->golongan, $r->tanggal_lahir, $r->tmt_pensiun])->all(),
            'total' => null,
            'angka' => $rows->groupBy(fn ($r) => substr($r->tmt_pensiun, 0, 4))->map->count()->sortKeys()->all(),
        ];
    }

    private function abk(array $p): array
    {
        $rows = DB::table('anjab_abks as a')
            ->join('unit_kerjas as uk', 'uk.id', '=', 'a.unit_kerja_id')
            ->join('instansis as i', 'i.id', '=', 'a.instansi_id')
            ->join('jabatans as j', 'j.id', '=', 'a.jabatan_id')
            ->leftJoin(DB::raw('(SELECT unit_kerja_id, jabatan_id, COUNT(*) as n FROM pegawais WHERE is_active = 1 GROUP BY unit_kerja_id, jabatan_id) pg'),
                fn ($jn) => $jn->on('pg.unit_kerja_id', '=', 'a.unit_kerja_id')->on('pg.jabatan_id', '=', 'a.jabatan_id'))
            ->when($p['instansi_id'] ?? null, fn ($q, $x) => $q->where('a.instansi_id', $x))
            ->when($p['tahun'] ?? null, fn ($q, $x) => $q->where('a.tahun', $x))
            ->orderBy('i.nama')->orderBy('uk.nama')
            ->get(['i.nama as instansi', 'uk.nama as unit', 'j.nama as jabatan', 'a.tahun', 'a.total_beban_kerja', 'a.waktu_kerja_efektif',
                'a.kebutuhan_hitung', 'a.kebutuhan', 'a.status', 'pg.n']);

        $baris = $rows->map(function ($r) {
            $existing = (int) ($r->n ?? 0);
            $ej = $existing ? round($r->total_beban_kerja / ($existing * $r->waktu_kerja_efektif), 2) : null;

            return [$r->instansi, $r->unit, $r->jabatan, $r->tahun, round($r->total_beban_kerja / 60), (float) $r->kebutuhan_hitung, $r->kebutuhan,
                $existing, $ej, Referensi::pej($ej)['nilai'] ?? '-', $r->status];
        })->all();

        return [
            'kolom' => ['Instansi', 'Unit kerja', 'Jabatan', 'Tahun', 'Beban (jam)', 'Hitung', 'Kebutuhan', 'Existing', 'EJ', 'PEJ', 'Status'],
            'baris' => $baris,
            'total' => null,
            'angka' => ['abk' => count($baris), 'final' => collect($baris)->where(10, 'final')->count()],
        ];
    }

    private function tidakBergerak(array $filters, int $bulan): array
    {
        $rows = $this->histori->analisis(empty($filters['instansi_id']) ? 'instansi' : 'unit', $filters, $bulan, true);

        return [
            'kolom' => ['Nama', 'Induk', 'Kebutuhan', "Existing {$bulan} bln lalu", 'Existing kini', 'Selisih', 'Terakhir bergerak', 'Kondisi'],
            'baris' => $rows->map(fn ($r) => [$r['nama'], $r['induk'], $r['kebutuhan'], $r['existing_awal'], $r['existing'], $r['selisih'],
                $r['terakhir_bergerak'] ? substr($r['terakhir_bergerak'], 0, 10) : 'belum pernah', $r['kondisi']])->all(),
            'total' => null,
            'angka' => ['tidak_bergerak' => $rows->count(), 'bermasalah' => $rows->whereIn('kondisi', ['kurang', 'kosong', 'lebih'])->count()],
        ];
    }

    private function peringatan(array $p): array
    {
        $rows = Peringatan::with(['instansi:id,nama'])->whereIn('status', ['aktif', 'ditindaklanjuti'])
            ->when($p['instansi_id'] ?? null, fn ($q, $x) => $q->where('instansi_id', $x))
            ->orderByRaw("CASE tingkat WHEN 'kritis' THEN 1 WHEN 'tinggi' THEN 2 WHEN 'sedang' THEN 3 ELSE 4 END")->get();

        return [
            'kolom' => ['Tingkat', 'Jenis', 'Instansi', 'Peringatan', 'Keterangan', 'Sejak', 'Status', 'Tindak lanjut'],
            'baris' => $rows->map(fn ($r) => [strtoupper($r->tingkat), PeringatanService::ATURAN[$r->kode] ?? $r->kode, $r->instansi?->nama, $r->judul, $r->pesan,
                $r->pertama_terdeteksi_at?->format('Y-m-d'), $r->status, $r->catatan_tindak_lanjut])->all(),
            'total' => null,
            'angka' => $rows->groupBy('tingkat')->map->count()->all(),
        ];
    }
}
