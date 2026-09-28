<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\UsulanStatus;
use App\Models\Peringatan;
use App\Models\User;
use App\Models\Usulan;
use App\Notifications\RingkasanPeringatan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Sistem peringatan dini. Setiap aturan menghasilkan daftar temuan; temuan disimpan
 * (upsert per kunci objek), dan peringatan yang kondisinya sudah hilang otomatis ditandai selesai.
 */
class PeringatanService
{
    public const ATURAN = [
        'jabatan_kosong' => 'Jabatan dibutuhkan tetapi kosong',
        'kekurangan_kritis' => 'Pemenuhan pegawai unit sangat rendah',
        'unit_gemuk' => 'Unit kelebihan pegawai (gemuk)',
        'stagnan' => 'Unit kekurangan tanpa pergerakan pegawai',
        'data_usang' => 'Data existing tidak diperbarui',
        'abk_kedaluwarsa' => 'ABK belum ada / kedaluwarsa',
        'pensiun_mendatang' => 'Pensiun mendatang memperbesar kekurangan',
        'usulan_tertahan' => 'Usulan tertahan di satu tahap',
        'formasi_belum_terisi' => 'Formasi ditetapkan belum terisi',
        'siasn_gagal' => 'Sinkronisasi SIASN gagal',
    ];

    public function __construct(private MonitoringService $monitoring, private HistoriService $histori) {}

    /**
     * Jalankan semua aturan. Mengembalikan jumlah peringatan baru, aktif, dan yang selesai.
     */
    public function deteksi(bool $kirimNotifikasi = true): array
    {
        $cfg = config('simonkeb.peringatan');
        $temuan = collect()
            ->merge($this->jabatanKosong())
            ->merge($this->unitTidakSeimbang($cfg))
            ->merge($this->stagnan($cfg))
            ->merge($this->dataUsang($cfg))
            ->merge($this->abkKedaluwarsa($cfg))
            ->merge($this->pensiunMendatang($cfg))
            ->merge($this->usulanTertahan($cfg))
            ->merge($this->formasiBelumTerisi($cfg))
            ->merge($this->siasnGagal());

        $now = now();
        $baru = collect();

        DB::transaction(function () use ($temuan, $now, $baru) {
            foreach ($temuan as $t) {
                $p = Peringatan::firstOrNew(['kunci' => $t['kunci']]);
                $isBaru = ! $p->exists || $p->status === 'selesai';
                $p->fill($t + [
                    'terakhir_terdeteksi_at' => $now,
                    'pertama_terdeteksi_at' => $isBaru ? $now : $p->pertama_terdeteksi_at,
                    'status' => $isBaru ? 'aktif' : $p->status,
                    'selesai_at' => null,
                ]);
                if ($isBaru) {
                    $p->catatan_tindak_lanjut = null;
                    $p->ditangani_oleh = null;
                }
                $p->save();
                if ($isBaru) {
                    $baru->push($p);
                }
            }

            // kondisi yang sudah tidak terdeteksi dianggap selesai
            Peringatan::whereIn('status', ['aktif', 'ditindaklanjuti'])
                ->whereNotIn('kunci', $temuan->pluck('kunci'))
                ->update(['status' => 'selesai', 'selesai_at' => $now]);
        });

        if ($kirimNotifikasi) {
            $this->beritahu($baru->whereIn('tingkat', ['kritis', 'tinggi']));
        }

        return [
            'baru' => $baru->count(),
            'aktif' => Peringatan::whereIn('status', ['aktif', 'ditindaklanjuti'])->count(),
            'selesai' => Peringatan::where('status', 'selesai')->where('selesai_at', $now)->count(),
        ];
    }

    private function jabatanKosong(): Collection
    {
        return $this->monitoring->positions(['status' => 'kosong'], 5000)->map(fn ($p) => [
            'kode' => 'jabatan_kosong',
            'tingkat' => in_array($p['jenis'], ['jpt_utama', 'jpt_madya', 'jpt_pratama', 'administrator', 'pengawas'], true) ? 'tinggi' : 'sedang',
            'instansi_id' => $p['instansi_id'], 'unit_kerja_id' => $p['unit_kerja_id'], 'jabatan_id' => $p['jabatan_id'],
            'kunci' => "jabatan_kosong:{$p['unit_kerja_id']}:{$p['jabatan_id']}",
            'judul' => "{$p['jabatan_nama']} kosong",
            'pesan' => "{$p['unit_nama']} ({$p['instansi_nama']}) membutuhkan {$p['kebutuhan']} {$p['jabatan_nama']} tetapi belum ada pegawai.",
            'data' => ['kebutuhan' => $p['kebutuhan'], 'formasi' => $p['formasi']],
            'url' => "/monitoring/unit/{$p['unit_kerja_id']}",
        ]);
    }

    /** Unit dengan pemenuhan sangat rendah atau terlalu gemuk (angka unit sendiri). */
    private function unitTidakSeimbang(array $cfg): Collection
    {
        $rows = $this->monitoring->positionsQuery()
            ->join('unit_kerjas as uk', 'uk.id', '=', 'p.unit_kerja_id')
            ->join('instansis as i', 'i.id', '=', 'p.instansi_id')
            ->groupBy('p.unit_kerja_id', 'p.instansi_id', 'uk.nama', 'i.nama')
            ->selectRaw('p.unit_kerja_id, p.instansi_id, uk.nama as unit_nama, i.nama as instansi_nama, SUM(p.kebutuhan) as kebutuhan, SUM(p.existing) as existing')
            ->havingRaw('SUM(p.kebutuhan) > 0')
            ->get();

        return $rows->map(function ($r) use ($cfg) {
            $persen = round($r->existing / $r->kebutuhan * 100, 1);

            if ($persen < $cfg['kritis_persen']) {
                return [
                    'kode' => 'kekurangan_kritis', 'tingkat' => 'kritis',
                    'instansi_id' => $r->instansi_id, 'unit_kerja_id' => $r->unit_kerja_id, 'jabatan_id' => null,
                    'kunci' => "kekurangan_kritis:{$r->unit_kerja_id}",
                    'judul' => "Pemenuhan {$r->unit_nama} hanya {$persen}%",
                    'pesan' => "Existing {$r->existing} dari kebutuhan {$r->kebutuhan} ({$r->instansi_nama}).",
                    'data' => ['persen' => $persen, 'kebutuhan' => (int) $r->kebutuhan, 'existing' => (int) $r->existing],
                    'url' => "/monitoring/unit/{$r->unit_kerja_id}",
                ];
            }
            if ($persen > $cfg['gemuk_persen']) {
                return [
                    'kode' => 'unit_gemuk', 'tingkat' => 'sedang',
                    'instansi_id' => $r->instansi_id, 'unit_kerja_id' => $r->unit_kerja_id, 'jabatan_id' => null,
                    'kunci' => "unit_gemuk:{$r->unit_kerja_id}",
                    'judul' => "{$r->unit_nama} gemuk ({$persen}%)",
                    'pesan' => "Existing {$r->existing} melebihi kebutuhan {$r->kebutuhan} ({$r->instansi_nama}). Pertimbangkan redistribusi.",
                    'data' => ['persen' => $persen, 'kebutuhan' => (int) $r->kebutuhan, 'existing' => (int) $r->existing],
                    'url' => "/redistribusi?instansi_id={$r->instansi_id}",
                ];
            }

            return null;
        })->filter()->values();
    }

    /** Unit yang kekurangan/kosong tetapi tidak ada pergerakan pegawai sama sekali selama N bulan. */
    private function stagnan(array $cfg): Collection
    {
        $bulan = $cfg['stagnan_bulan'];

        return $this->histori->analisis('unit', [], $bulan, true)
            ->whereIn('kondisi', ['kurang', 'kosong'])
            ->map(fn ($r) => [
                'kode' => 'stagnan', 'tingkat' => 'tinggi',
                'instansi_id' => $r['instansi_id'], 'unit_kerja_id' => $r['unit_kerja_id'], 'jabatan_id' => null,
                'kunci' => "stagnan:{$r['unit_kerja_id']}",
                'judul' => "{$r['nama']} tidak bergerak {$bulan} bulan",
                'pesan' => 'Kekurangan '.abs($r['selisih'])." pegawai ({$r['induk']}) tanpa satu pun mutasi/pengangkatan dalam {$bulan} bulan terakhir.",
                'data' => ['selisih' => $r['selisih'], 'terakhir_bergerak' => $r['terakhir_bergerak']],
                'url' => "/histori?instansi_id={$r['instansi_id']}&level=unit&bulan={$bulan}",
            ])->values();
    }

    /** Instansi yang data pegawainya tidak diperbarui (manual, impor, maupun SIASN) selama N bulan. */
    private function dataUsang(array $cfg): Collection
    {
        $batas = now()->subMonths($cfg['data_usang_bulan']);

        return DB::table('instansis as i')
            ->where('i.is_active', true)
            ->leftJoin('pegawais as p', 'p.instansi_id', '=', 'i.id')
            ->groupBy('i.id', 'i.nama')
            ->selectRaw('i.id, i.nama, MAX(p.updated_at) as terakhir, COUNT(p.id) as jumlah')
            ->get()
            ->filter(fn ($r) => $r->jumlah > 0 && $r->terakhir < $batas)
            ->map(fn ($r) => [
                'kode' => 'data_usang', 'tingkat' => 'sedang',
                'instansi_id' => $r->id, 'unit_kerja_id' => null, 'jabatan_id' => null,
                'kunci' => "data_usang:{$r->id}",
                'judul' => "Data existing {$r->nama} tidak diperbarui",
                'pesan' => 'Perubahan terakhir data pegawai '.substr((string) $r->terakhir, 0, 10).'. Lakukan pemutakhiran atau sinkron SIASN.',
                'data' => ['terakhir' => $r->terakhir],
                'url' => "/siasn?instansi_id={$r->id}",
            ])->values();
    }

    /** Per instansi: jabatan berpegawai yang belum punya ABK final atau ABK-nya terlalu lama. */
    private function abkKedaluwarsa(array $cfg): Collection
    {
        $minTahun = (int) now()->format('Y') - $cfg['abk_maks_umur_tahun'];

        // posisi berpegawai beserta tahun ABK final terbarunya
        $posisi = DB::table('pegawais as p')
            ->where('p.is_active', true)
            ->leftJoin(DB::raw("(SELECT unit_kerja_id, jabatan_id, MAX(tahun) as tahun FROM anjab_abks WHERE status = 'final' GROUP BY unit_kerja_id, jabatan_id) a"),
                fn ($j) => $j->on('a.unit_kerja_id', '=', 'p.unit_kerja_id')->on('a.jabatan_id', '=', 'p.jabatan_id'))
            ->groupBy('p.instansi_id', 'p.unit_kerja_id', 'p.jabatan_id', 'a.tahun')
            ->get(['p.instansi_id', 'p.unit_kerja_id', 'p.jabatan_id', 'a.tahun']);
        $nama = DB::table('instansis')->pluck('nama', 'id');

        $rows = $posisi->groupBy('instansi_id')->map(fn ($list, $id) => (object) [
            'instansi_id' => (int) $id,
            'nama' => $nama[$id] ?? '-',
            'tanpa_abk' => $list->whereNull('tahun')->count(),
            'lama' => $list->filter(fn ($r) => $r->tahun !== null && $r->tahun < $minTahun)->count(),
        ]);

        return $rows->filter(fn ($r) => $r->tanpa_abk + $r->lama > 0)->map(fn ($r) => [
            'kode' => 'abk_kedaluwarsa', 'tingkat' => 'rendah',
            'instansi_id' => $r->instansi_id, 'unit_kerja_id' => null, 'jabatan_id' => null,
            'kunci' => "abk_kedaluwarsa:{$r->instansi_id}",
            'judul' => ($r->tanpa_abk + $r->lama)." jabatan {$r->nama} tanpa ABK mutakhir",
            'pesan' => "{$r->tanpa_abk} jabatan berpegawai belum memiliki ABK final dan {$r->lama} jabatan memakai ABK sebelum {$minTahun}.",
            'data' => ['tanpa_abk' => (int) $r->tanpa_abk, 'lama' => (int) $r->lama],
            'url' => "/anjab?instansi_id={$r->instansi_id}",
        ])->values();
    }

    /** Per unit: pensiun dalam N bulan yang membuat unit (makin) kekurangan. */
    private function pensiunMendatang(array $cfg): Collection
    {
        $batas = now()->addMonths($cfg['pensiun_bulan'])->toDateString();

        $pensiun = DB::table('pegawais')->where('is_active', true)->whereNotNull('tmt_pensiun')
            ->whereBetween('tmt_pensiun', [now()->toDateString(), $batas])
            ->groupBy('unit_kerja_id', 'jabatan_id')
            ->selectRaw('unit_kerja_id, jabatan_id, COUNT(*) as jumlah')
            ->get()->keyBy(fn ($r) => $r->unit_kerja_id.'-'.$r->jabatan_id);

        if ($pensiun->isEmpty()) {
            return collect();
        }

        return $this->monitoring->positions([], 100000)
            ->filter(fn ($p) => $pensiun->has($p['unit_kerja_id'].'-'.$p['jabatan_id']))
            ->map(fn ($p) => $p + ['akan_pensiun' => (int) $pensiun[$p['unit_kerja_id'].'-'.$p['jabatan_id']]->jumlah])
            ->filter(fn ($p) => $p['kebutuhan'] > 0 && $p['kebutuhan'] > $p['existing'] - $p['akan_pensiun'] + $p['formasi'])
            ->groupBy('unit_kerja_id')
            ->map(function ($rows, $unitId) use ($cfg) {
                $f = $rows->first();

                return [
                    'kode' => 'pensiun_mendatang', 'tingkat' => 'sedang',
                    'instansi_id' => $f['instansi_id'], 'unit_kerja_id' => (int) $unitId, 'jabatan_id' => null,
                    'kunci' => "pensiun_mendatang:{$unitId}",
                    'judul' => $rows->sum('akan_pensiun')." pegawai {$f['unit_nama']} pensiun dalam {$cfg['pensiun_bulan']} bulan",
                    'pesan' => 'Jabatan terdampak: '.$rows->pluck('jabatan_nama')->unique()->implode(', ').'. Belum tertutup formasi.',
                    'data' => ['jumlah' => $rows->sum('akan_pensiun')],
                    'url' => "/proyeksi?instansi_id={$f['instansi_id']}&unit_kerja_id={$unitId}",
                ];
            })->values();
    }

    private function usulanTertahan(array $cfg): Collection
    {
        $proses = [UsulanStatus::Diajukan, UsulanStatus::VerifikasiBkn, UsulanStatus::PertimbanganTeknis, UsulanStatus::ValidasiKemenpan];

        return Usulan::with('instansi:id,nama')
            ->whereIn('status', array_merge($proses, [UsulanStatus::Dikembalikan]))
            ->withMax('logs as terakhir', 'created_at')
            ->get()
            ->map(function ($u) use ($cfg) {
                $hari = (int) Carbon::parse($u->terakhir ?? $u->updated_at)->diffInDays(now());
                $dikembalikan = $u->status === UsulanStatus::Dikembalikan;
                $batas = $dikembalikan ? $cfg['usulan_dikembalikan_hari'] : $cfg['usulan_tertahan_hari'];

                if ($hari < $batas) {
                    return null;
                }

                return [
                    'kode' => 'usulan_tertahan', 'tingkat' => $dikembalikan ? 'sedang' : 'tinggi',
                    'instansi_id' => $u->instansi_id, 'unit_kerja_id' => null, 'jabatan_id' => null,
                    'kunci' => "usulan_tertahan:{$u->id}:{$u->status->value}",
                    'judul' => "Usulan {$u->nomor} tertahan {$hari} hari",
                    'pesan' => "Status {$u->status->label()} sejak {$hari} hari ({$u->instansi?->nama}).",
                    'data' => ['hari' => $hari, 'status' => $u->status->value],
                    'url' => "/usulan/{$u->id}",
                ];
            })->filter()->values();
    }

    private function formasiBelumTerisi(array $cfg): Collection
    {
        return DB::table('usulan_details as d')
            ->join('usulans as u', 'u.id', '=', 'd.usulan_id')
            ->join('instansis as i', 'i.id', '=', 'u.instansi_id')
            ->where('u.status', UsulanStatus::Ditetapkan->value)
            ->where('u.ditetapkan_at', '<=', now()->subMonths($cfg['formasi_belum_terisi_bulan']))
            ->groupBy('u.id', 'u.nomor', 'u.instansi_id', 'i.nama')
            ->selectRaw('u.id, u.nomor, u.instansi_id, i.nama, SUM(CASE WHEN COALESCE(d.jumlah_ditetapkan,0) > d.jumlah_terisi THEN COALESCE(d.jumlah_ditetapkan,0) - d.jumlah_terisi ELSE 0 END) as sisa')
            ->get()
            ->filter(fn ($r) => $r->sisa > 0)
            ->map(fn ($r) => [
                'kode' => 'formasi_belum_terisi', 'tingkat' => 'sedang',
                'instansi_id' => $r->instansi_id, 'unit_kerja_id' => null, 'jabatan_id' => null,
                'kunci' => "formasi_belum_terisi:{$r->id}",
                'judul' => "{$r->sisa} formasi {$r->nama} belum terisi",
                'pesan' => "Formasi usulan {$r->nomor} telah ditetapkan lebih dari {$cfg['formasi_belum_terisi_bulan']} bulan tetapi belum terisi seluruhnya.",
                'data' => ['sisa' => (int) $r->sisa],
                'url' => '/formasi?status=belum',
            ])->values();
    }

    private function siasnGagal(): Collection
    {
        $terakhir = DB::table('siasn_sync_logs')
            ->whereIn('id', DB::table('siasn_sync_logs')->groupBy('instansi_id', 'jenis')->selectRaw('MAX(id)'))
            ->where('status', 'gagal')
            ->get();

        return $terakhir->map(fn ($l) => [
            'kode' => 'siasn_gagal', 'tingkat' => 'tinggi',
            'instansi_id' => $l->instansi_id, 'unit_kerja_id' => null, 'jabatan_id' => null,
            'kunci' => "siasn_gagal:{$l->instansi_id}:{$l->jenis}",
            'judul' => "Sinkronisasi SIASN ({$l->jenis}) gagal",
            'pesan' => (json_decode($l->pesan ?? '[]', true)[0] ?? 'Periksa log sinkronisasi.'),
            'data' => ['log_id' => $l->id],
            'url' => "/siasn?instansi_id={$l->instansi_id}",
        ]);
    }

    /** Ringkasan peringatan penting baru ke admin dan operator instansi terkait. */
    private function beritahu(Collection $baru): void
    {
        if ($baru->isEmpty()) {
            return;
        }

        $admins = User::where('role', Role::Admin)->where('is_active', true)->get();
        Notification::send($admins, new RingkasanPeringatan($baru->count(), $baru->take(5)->pluck('judul')->all()));

        foreach ($baru->groupBy('instansi_id') as $instansiId => $list) {
            if (! $instansiId) {
                continue;
            }
            $operators = User::where('role', Role::OperatorInstansi)->where('instansi_id', $instansiId)->where('is_active', true)->get();
            Notification::send($operators, new RingkasanPeringatan($list->count(), $list->take(5)->pluck('judul')->all()));
        }
    }
}
