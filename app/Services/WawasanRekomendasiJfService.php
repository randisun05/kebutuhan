<?php

namespace App\Services;

use App\Models\SumberData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Wawasan dari data rekomendasi JF satu instansi pembina: cakupan, instansi sasaran penataan, jalur karier tersumbat,
 * peluang pendampingan, antrean menunggu penetapan, dan pola pemotongan penetapan. Peran sumber angka ditentukan dari master
 * (bezetting = sumber bezetting pertama; rekomendasi = sumber kebutuhan pertama; penetapan = sumber kebutuhan kedua), tanpa
 * kode sumber yang ditulis di sini. Semua angka bersifat foto saat dokumen, bukan kondisi kini.
 */
class WawasanRekomendasiJfService
{
    /** Batas tampil daftar di layar; ekspor Excel memuat seluruhnya. */
    public const BATAS_LAYAR = 50;

    public function hitung(int $pembinaId, ?\DateTimeInterface $sekarang = null): array
    {
        $sekarang = $sekarang ? Carbon::instance($sekarang) : now();
        $cfg = config('simonkeb.wawasan');

        $sumber = SumberData::where('pembina_id', $pembinaId)->where('is_active', true)->where('adapter', 'fakta')->orderBy('urutan')->get();
        $bezetting = $sumber->firstWhere('jenis', 'bezetting');
        $kebutuhan = $sumber->where('jenis', 'kebutuhan')->values();
        $rekom = $kebutuhan->get(0);
        $penetapan = $kebutuhan->get(1);

        $peran = ['bezetting' => $bezetting?->nama, 'rekom' => $rekom?->nama, 'penetapan' => $penetapan?->nama];
        if (! $bezetting || ! $rekom) {
            return ['peran' => $peran, 'lengkap' => false];
        }

        $header = DB::table('rekomendasi_jf as r')
            ->join('instansi_alias as a', 'a.id', '=', 'r.alias_id')
            ->join('jf_jabatan_pokok as j', 'j.id', '=', 'r.jabatan_pokok_id')
            ->where('j.pembina_id', $pembinaId)->where('j.is_active', true)
            ->get(['r.id', 'r.alias_id', 'r.jabatan_pokok_id', 'r.status_menpan', 'r.pokja', 'a.nama as instansi', 'a.jenis_instansi', 'j.nama as jabatan', 'j.urutan as urutan_jabatan'])
            ->keyBy('id');

        // jumlah jenjang dan posisi jenjang per jabatan (untuk jalur karier)
        $posisi = DB::table('jf_jabatan_jenjang')->get(['jabatan_pokok_id', 'jenjang_id', 'urutan_kolom'])
            ->groupBy('jabatan_pokok_id')->map(fn ($g) => $g->pluck('urutan_kolom', 'jenjang_id'));

        // fakta terbaru: [header][peran][jenjang] = nilai
        $fakta = [];
        $adaRekom = $adaPenetapan = [];
        foreach (DB::table('kebutuhan_fakta')->where('is_terbaru', true)->whereIn('rekomendasi_jf_id', $header->keys())->whereNotNull('nilai')
            ->whereIn('sumber_id', array_filter([$bezetting->id, $rekom->id, $penetapan?->id]))->get(['rekomendasi_jf_id', 'sumber_id', 'jenjang_id', 'nilai']) as $f) {
            $p = $f->sumber_id === $bezetting->id ? 'bezetting' : ($f->sumber_id === $rekom->id ? 'rekom' : 'penetapan');
            $fakta[$f->rekomendasi_jf_id][$p][$f->jenjang_id] = (int) $f->nilai;
            if ($p === 'rekom') {
                $adaRekom[$f->rekomendasi_jf_id] = true;
            }
            if ($p === 'penetapan' && $f->nilai > 0) {
                $adaPenetapan[$f->rekomendasi_jf_id] = true;
            }
        }
        $jumlah = fn (int $id, string $p) => array_sum($fakta[$id][$p] ?? []);

        // surat rekomendasi terakhir per header (untuk umur antrean)
        $suratTerakhir = [];
        foreach (DB::table('rekomendasi_jf_surat')->where('jenis', 'rekomendasi')->whereIn('rekomendasi_jf_id', $header->keys())->orderBy('tanggal')->get(['rekomendasi_jf_id', 'nomor', 'tanggal']) as $s) {
            $suratTerakhir[$s->rekomendasi_jf_id] = $s; // urut naik: yang terakhir menimpa
        }

        $sudahRekom = fn ($h) => isset($adaRekom[$h->id]);
        $sudahTetap = fn ($h) => isset($adaPenetapan[$h->id]) || $h->status_menpan === 'ditetapkan';

        return [
            'lengkap' => true,
            'peran' => $peran,
            'ambang' => $cfg,
            'cakupan' => $this->cakupan($header, $sudahRekom, $sudahTetap),
            'sasaran' => $this->sasaran($header, $jumlah, $sudahRekom, (int) $cfg['ambang_sasaran']),
            'tersumbat' => $this->tersumbat($header, $fakta, $posisi, (int) $cfg['ambang_tersumbat']),
            'pendampingan' => $this->pendampingan($header, $sudahRekom),
            'antrean' => $this->antrean($header, $sudahRekom, $sudahTetap, $suratTerakhir, $sekarang),
            'pemotongan' => $this->pemotongan($header, $jumlah, $sudahTetap, (int) $cfg['ambang_pemotongan']),
        ];
    }

    private function cakupan(Collection $header, \Closure $sudahRekom, \Closure $sudahTetap): array
    {
        return $header->groupBy('jabatan_pokok_id')->map(function ($g) use ($sudahRekom, $sudahTetap) {
            $n = $g->count();
            $rekom = $g->filter($sudahRekom)->count();
            $tetap = $g->filter($sudahTetap)->count();

            return ['jabatan' => $g->first()->jabatan, 'urutan' => $g->first()->urutan_jabatan, 'instansi' => $n, 'sudah_rekom' => $rekom, 'sudah_ditetapkan' => $tetap, 'belum_usul' => $n - $rekom];
        })->sortBy('urutan')->values()->all();
    }

    /** Instansi dengan JF sangat sedikit. Hanya yang sudah punya rekomendasi dibedakan "benar-benar nol" dari "belum ada data". */
    private function sasaran(Collection $header, \Closure $jumlah, \Closure $sudahRekom, int $ambang): array
    {
        $baris = $header->groupBy('alias_id')->map(function ($g) use ($jumlah, $sudahRekom) {
            $p = $g->first();

            return [
                'instansi' => $p->instansi, 'jenis' => $p->jenis_instansi, 'pokja' => $p->pokja,
                'bezetting' => $g->sum(fn ($h) => $jumlah($h->id, 'bezetting')), 'rekom' => $g->sum(fn ($h) => $jumlah($h->id, 'rekom')),
                'punya_rekom' => $g->contains($sudahRekom), 'header_id' => $g->first()->id,
            ];
        });
        $kandidat = $baris->filter(fn ($b) => $b['bezetting'] < $ambang)->map(fn ($b) => $b + ['kategori' => $b['punya_rekom'] ? ($b['bezetting'] === 0 ? 'nol' : 'kurang') : 'belum_ada_data']);

        return [
            'ringkas' => [
                'nol' => $kandidat->where('kategori', 'nol')->count(), 'kurang' => $kandidat->where('kategori', 'kurang')->count(),
                'belum_ada_data' => $kandidat->where('kategori', 'belum_ada_data')->count(), 'total_instansi' => $baris->count(),
            ],
            'daftar' => $kandidat->where('kategori', '!=', 'belum_ada_data')->sortBy([['bezetting', 'asc'], ['rekom', 'desc']])->values()->all(),
        ];
    }

    /** Jenjang bawah banyak, dua jenjang teratas kosong: jalur kenaikan jenjang tersumbat. Hanya jabatan dengan 3 jenjang atau lebih. */
    private function tersumbat(Collection $header, array $fakta, Collection $posisi, int $ambang): array
    {
        $daftar = [];
        foreach ($header as $h) {
            $pos = $posisi->get($h->jabatan_pokok_id);
            $n = $pos ? $pos->count() : 0;
            $bez = $fakta[$h->id]['bezetting'] ?? null;
            if ($n < 3 || ! $bez) {
                continue;
            }
            $bawah = $atas = 0;
            foreach ($bez as $jenjangId => $nilai) {
                ($pos[$jenjangId] ?? 0) > $n - 2 ? $atas += $nilai : $bawah += $nilai;
            }
            if ($bawah >= $ambang && $atas === 0) {
                $daftar[] = ['instansi' => $h->instansi, 'jenis' => $h->jenis_instansi, 'jabatan' => $h->jabatan, 'bawah' => $bawah, 'atas' => $atas, 'header_id' => $h->id];
            }
        }
        usort($daftar, fn ($a, $b) => $b['bawah'] <=> $a['bawah']);

        return ['jumlah' => count($daftar), 'daftar' => $daftar];
    }

    /** Instansi yang sudah direkom untuk jabatan acuan (urutan terkecil) tetapi belum usul pada jabatan lain. */
    private function pendampingan(Collection $header, \Closure $sudahRekom): array
    {
        $acuan = $header->min('urutan_jabatan');
        $jenis = [];
        $daftar = [];
        foreach ($header->groupBy('alias_id') as $g) {
            $a = $g->firstWhere('urutan_jabatan', $acuan);
            if (! $a || ! $sudahRekom($a)) {
                continue;
            }
            $belum = $g->filter(fn ($h) => $h->urutan_jabatan !== $acuan && ! $sudahRekom($h))->pluck('jabatan')->sort()->values();
            $k = $a->jenis_instansi ?? '-';
            $jenis[$k]['jenis'] = $k;
            $jenis[$k]['instansi'] = ($jenis[$k]['instansi'] ?? 0) + 1;
            foreach ($belum as $jab) {
                $jenis[$k]['belum'][$jab] = ($jenis[$k]['belum'][$jab] ?? 0) + 1;
            }
            if ($belum->isNotEmpty()) {
                $daftar[] = ['instansi' => $a->instansi, 'jenis' => $a->jenis_instansi, 'pokja' => $a->pokja, 'belum_usul' => $belum->implode(', '), 'jumlah' => $belum->count(), 'header_id' => $a->id];
            }
        }
        $jabatanLain = $header->where('urutan_jabatan', '!=', $acuan)->pluck('jabatan', 'jabatan_pokok_id')->unique()->sort()->values()->all();
        usort($daftar, fn ($a, $b) => [$b['jumlah'], $a['instansi']] <=> [$a['jumlah'], $b['instansi']]);

        return [
            'acuan' => $header->firstWhere('urutan_jabatan', $acuan)?->jabatan, 'jabatan_lain' => $jabatanLain,
            'per_jenis' => collect($jenis)->sortByDesc('instansi')->values()->all(), 'daftar' => $daftar,
        ];
    }

    /** Rekomendasi selesai tetapi belum ada penetapan, menurut umur surat rekomendasi terakhir. */
    private function antrean(Collection $header, \Closure $sudahRekom, \Closure $sudahTetap, array $suratTerakhir, Carbon $sekarang): array
    {
        $daftar = [];
        foreach ($header->filter(fn ($h) => $sudahRekom($h) && ! $sudahTetap($h)) as $h) {
            $s = $suratTerakhir[$h->id] ?? null;
            $umur = $s?->tanggal ? (int) Carbon::parse($s->tanggal)->diffInDays($sekarang) : null;
            $daftar[] = ['instansi' => $h->instansi, 'jenis' => $h->jenis_instansi, 'jabatan' => $h->jabatan, 'nomor' => $s?->nomor, 'tanggal' => $s?->tanggal, 'umur_hari' => $umur, 'header_id' => $h->id];
        }
        $umur = collect($daftar)->pluck('umur_hari');
        usort($daftar, fn ($a, $b) => ($b['umur_hari'] ?? -1) <=> ($a['umur_hari'] ?? -1));

        return [
            'jumlah' => count($daftar),
            'kelompok' => [
                'kurang_90' => $umur->filter(fn ($u) => $u !== null && $u < 90)->count(), 'd90_180' => $umur->filter(fn ($u) => $u !== null && $u >= 90 && $u <= 180)->count(),
                'd181_365' => $umur->filter(fn ($u) => $u !== null && $u > 180 && $u <= 365)->count(), 'lebih_365' => $umur->filter(fn ($u) => $u !== null && $u > 365)->count(),
                'tanpa_tanggal' => $umur->filter(fn ($u) => $u === null)->count(),
            ],
            'daftar' => $daftar,
        ];
    }

    /** Penetapan dibanding rekomendasi per jenis instansi. Rasio di atas ambang ditandai perlu diperiksa. */
    private function pemotongan(Collection $header, \Closure $jumlah, \Closure $sudahTetap, int $ambang): array
    {
        return $header->filter($sudahTetap)->groupBy(fn ($h) => $h->jenis_instansi ?? '-')->map(function ($g, $jenis) use ($jumlah, $ambang) {
            $rek = $g->sum(fn ($h) => $jumlah($h->id, 'rekom'));
            $pen = $g->sum(fn ($h) => $jumlah($h->id, 'penetapan'));
            $persen = $rek > 0 ? round($pen / $rek * 100, 1) : null;

            return ['jenis' => $jenis, 'baris' => $g->count(), 'rekom' => $rek, 'penetapan' => $pen, 'persen' => $persen, 'periksa' => $persen !== null && $persen > $ambang];
        })->sortByDesc('baris')->values()->all();
    }
}
