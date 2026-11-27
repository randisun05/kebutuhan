<?php

namespace App\Services;

use App\Models\RefInstansiBkn;
use App\Models\RekomendasiJf;
use App\Models\RekomendasiJfImpor;
use App\Models\RekomendasiJfPetaInstansi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Impor Excel rekap kebutuhan JF MASN dari tim data (sheet "Input Data (Instansi Baru)").
 * Hanya kolom isian yang dibaca; kolom rumus (bulan/tahun, total, status Menpan, selisih) diabaikan karena
 * dihitung ulang. Lowongan tidak disimpan: selalu bezetting − rekomendasi BKN.
 */
class RekomendasiJfImportService
{
    public const SHEET = 'Input Data (Instansi Baru)';

    /** Indeks kolom (0-based) pada sheet sumber. */
    private const K = [
        'instansi' => 3, 'jabatan' => 4, 'jenis_instansi' => 5, 'pokja' => 6, 'status_rekom' => 7,
        'no_rekom' => 9, 'tgl_rekom' => 10, 'link_rekom' => 11, 'no_kembali' => 12, 'tgl_kembali' => 13, 'jenis_rekom' => 14,
        'bezetting' => [17, 18, 19, 20], 'rekom_bkn' => [22, 23, 24, 25], 'lowongan' => [27, 28, 29, 30],
        'sesuai_bezetting' => 31, 'catatan' => 32, 'menpan_blok1' => [33, 34, 35, 36], 'menpan_blok2' => [37, 38, 39, 40],
        'no_menpan' => 41, 'tgl_menpan' => 42, 'ttd_jabatan' => 43, 'ttd_nama' => 44, 'wilayah' => 49, 'catatan2' => 50,
    ];

    private const BULAN = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7,
        'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
    ];

    /**
     * Baca dan validasi berkas tanpa menulis ke database.
     *
     * @return array{baris: array, peringatan: array, ringkas: array}
     */
    public function baca(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([self::SHEET]);
        try {
            $sheet = $reader->load($path)->getSheetByName(self::SHEET);
        } catch (Throwable $e) {
            throw new \RuntimeException('Berkas tidak dapat dibaca atau tidak memuat sheet "'.self::SHEET.'".', 0, $e);
        }
        if (! $sheet) {
            throw new \RuntimeException('Sheet "'.self::SHEET.'" tidak ditemukan pada berkas.');
        }

        $peringatan = [];
        $baris = [];
        $kunciTerlihat = [];
        $cocok = $this->pencocok();

        foreach ($sheet->toArray(null, false, false, false) as $i => $r) {
            $jabatan = trim((string) ($r[self::K['jabatan']] ?? ''));
            $nama = trim((string) ($r[self::K['instansi']] ?? ''));
            if ($nama === '' || ! isset(RekomendasiJf::JABATAN[$jabatan])) {
                continue; // baris judul, nomor kolom, atau kosong
            }
            $line = $i + 1;
            $rumpun = RekomendasiJf::JABATAN[$jabatan];
            $normal = RefInstansiBkn::normalisasiNama($nama);
            if (isset($kunciTerlihat["{$normal}|{$jabatan}"])) {
                $peringatan[] = "Baris {$line}: {$nama} / {$jabatan} muncul lagi (sebelumnya baris {$kunciTerlihat["{$normal}|{$jabatan}"]}); baris ini yang dipakai.";
            }
            $kunciTerlihat["{$normal}|{$jabatan}"] = $line;

            $bez = $this->angkaSet($r, self::K['bezetting'], $line, 'bezetting', $peringatan);
            $rek = $this->angkaSet($r, self::K['rekom_bkn'], $line, 'perhitungan formasi', $peringatan);
            $men2 = $this->angkaSet($r, self::K['menpan_blok2'], $line, 'penetapan Menpan', $peringatan);
            $men1 = $this->angkaSet($r, self::K['menpan_blok1'], $line, 'penetapan Menpan (blok 1)', $peringatan);
            $men = array_filter($men2, fn ($v) => $v !== null) ? $men2 : $men1;

            // jenjang: Pranata hanya tiga jenjang
            $jenjang = [];
            foreach (RekomendasiJf::JENJANG[$rumpun] as $idx => $kode) {
                $nilai = [$bez[$idx], $rek[$idx], $men[$idx]];
                if ($kode === null) {
                    if (array_sum(array_map(fn ($v) => $v ?? 0, $nilai)) > 0) {
                        $peringatan[] = "Baris {$line}: {$nama} / {$jabatan} berisi angka pada jenjang keempat, padahal {$jabatan} hanya punya tiga jenjang (diabaikan).";
                    }

                    continue;
                }
                if (array_filter($nilai, fn ($v) => $v !== null)) {
                    $jenjang[$kode] = ['bezetting_rekom' => $nilai[0], 'rekom_bkn' => $nilai[1], 'penetapan_menpan' => $nilai[2]];
                }
            }

            // lowongan di Excel dibanding hitung ulang (informasi)
            $lowExcel = $this->angkaSet($r, self::K['lowongan'], $line, 'lowongan', $peringatan, true);
            foreach (RekomendasiJf::JENJANG[$rumpun] as $idx => $kode) {
                if ($kode !== null && $bez[$idx] !== null && $rek[$idx] !== null && $lowExcel[$idx] !== null && $lowExcel[$idx] !== $bez[$idx] - $rek[$idx]) {
                    $peringatan[] = "Baris {$line}: lowongan {$kode} di Excel ({$lowExcel[$idx]}) berbeda dari bezetting − rekomendasi ({$bez[$idx]} − {$rek[$idx]}); dihitung ulang.";
                }
            }

            $surat = array_merge(
                $this->surat('rekomendasi', $r[self::K['no_rekom']] ?? null, $r[self::K['tgl_rekom']] ?? null, $line, $peringatan, ['tautan' => $this->teks($r[self::K['link_rekom']] ?? null)]),
                $this->surat('pengembalian', $r[self::K['no_kembali']] ?? null, $r[self::K['tgl_kembali']] ?? null, $line, $peringatan),
                $this->surat('penetapan_menpan', $r[self::K['no_menpan']] ?? null, $r[self::K['tgl_menpan']] ?? null, $line, $peringatan, [
                    'penandatangan_jabatan' => $this->teks($r[self::K['ttd_jabatan']] ?? null), 'penandatangan_nama' => $this->teks($r[self::K['ttd_nama']] ?? null),
                ]),
            );

            $adaMenpan = array_filter(array_column($jenjang, 'penetapan_menpan'), fn ($v) => $v !== null && $v > 0) || collect($surat)->contains('jenis', 'penetapan_menpan');
            $sesuai = $this->teks($r[self::K['sesuai_bezetting']] ?? null);

            $baris[] = [
                'nama_instansi' => $nama, 'nama_instansi_normal' => $normal, 'ref_instansi_id' => $cocok($nama, $normal),
                'jabatan_pokok' => $jabatan, 'rumpun' => $rumpun,
                'jenis_instansi' => $this->teks($r[self::K['jenis_instansi']] ?? null, 16), 'pokja' => $this->teks($r[self::K['pokja']] ?? null, 16),
                'wilayah_kerja' => $this->teks($r[self::K['wilayah']] ?? null),
                'status_rekom' => $this->teks($r[self::K['status_rekom']] ?? null, 40), 'jenis_rekom' => $this->teks($r[self::K['jenis_rekom']] ?? null, 24),
                'status_menpan' => $adaMenpan ? 'ditetapkan' : null,
                'ditetapkan_sesuai_bezetting' => $sesuai === null ? null : in_array(mb_strtolower($sesuai), ['ya', 'sesuai', 'true'], true),
                'catatan' => trim(implode("\n", array_filter([$this->teks($r[self::K['catatan']] ?? null), $this->teks($r[self::K['catatan2']] ?? null)]))) ?: null,
                'jenjang' => $jenjang, 'surat' => $surat,
            ];
        }

        return ['baris' => $baris, 'peringatan' => $peringatan, 'ringkas' => $this->ringkas($baris, $peringatan)];
    }

    /** Simpan hasil baca ke database. Baris yang sudah ada (instansi × jabatan) diperbarui, bukan digandakan. */
    public function simpan(array $hasil, ?User $user, string $namaBerkas): RekomendasiJfImpor
    {
        return DB::transaction(function () use ($hasil, $user, $namaBerkas) {
            $impor = RekomendasiJfImpor::create([
                'user_id' => $user?->id, 'nama_berkas' => mb_substr($namaBerkas, 0, 255), 'jumlah_baris' => count($hasil['baris']),
                'peringatan' => array_slice($hasil['peringatan'], 0, 300),
            ]);
            $dibuat = $diperbarui = 0;

            foreach ($hasil['baris'] as $b) {
                $data = collect($b)->except(['jenjang', 'surat'])->all() + ['impor_id' => $impor->id];
                $rekom = RekomendasiJf::where('nama_instansi_normal', $b['nama_instansi_normal'])->where('jabatan_pokok', $b['jabatan_pokok'])->first();
                if ($rekom) {
                    $rekom->update($data);
                    $rekom->jenjangs()->delete();
                    $rekom->surats()->delete();
                    $diperbarui++;
                } else {
                    $rekom = RekomendasiJf::create($data);
                    $dibuat++;
                }
                foreach ($b['jenjang'] as $kode => $angka) {
                    $rekom->jenjangs()->create(['jenjang' => $kode] + $angka);
                }
                foreach ($b['surat'] as $s) {
                    $rekom->surats()->create($s);
                }
            }

            $impor->update(['dibuat' => $dibuat, 'diperbarui' => $diperbarui]);

            return $impor;
        });
    }

    /** Petakan nama instansi Excel ke referensi BKN secara manual, lalu terapkan ke baris yang sudah ada. */
    public function petakanInstansi(string $namaNormal, string $namaExcel, ?int $refInstansiId): void
    {
        RekomendasiJfPetaInstansi::updateOrCreate(['nama_normal' => $namaNormal], ['nama_excel' => $namaExcel, 'ref_instansi_id' => $refInstansiId]);
        RekomendasiJf::where('nama_instansi_normal', $namaNormal)->update(['ref_instansi_id' => $refInstansiId]);
    }

    /** Fungsi pencocok nama instansi: pemetaan manual dulu, lalu kecocokan nama yang dinormalkan. */
    private function pencocok(): \Closure
    {
        $manual = RekomendasiJfPetaInstansi::whereNotNull('ref_instansi_id')->pluck('ref_instansi_id', 'nama_normal');
        $ref = [];
        foreach (RefInstansiBkn::get(['id', 'nama']) as $r) {
            $ref[RefInstansiBkn::normalisasiNama($r->nama)] ??= $r->id;
            $ref[preg_replace('/^pemerintah /', '', RefInstansiBkn::normalisasiNama($r->nama))] ??= $r->id;
        }

        return fn (string $nama, string $normal) => $manual[$normal] ?? $ref[$normal] ?? $ref[preg_replace('/^pemerintah /', '', $normal)] ?? null;
    }

    /** @return array<int, ?int> empat angka kolom jenjang; sel rumus/kosong/teks menjadi null. */
    private function angkaSet(array $r, array $kolom, int $line, string $label, array &$peringatan, bool $boleh_negatif = false): array
    {
        $hasil = [];
        foreach ($kolom as $c) {
            $v = $r[$c] ?? null;
            if ($v === null || $v === '' || is_bool($v) || (is_string($v) && str_starts_with($v, '='))) {
                $hasil[] = null;
            } elseif (is_numeric($v) && (float) $v == round((float) $v) && ($boleh_negatif || (float) $v >= 0)) {
                $hasil[] = (int) round((float) $v);
            } else {
                $peringatan[] = "Baris {$line}: nilai {$label} \"".mb_substr((string) $v, 0, 30).'" bukan bilangan bulat yang valid (diabaikan).';
                $hasil[] = null;
            }
        }

        return $hasil;
    }

    private function teks(mixed $v, int $maks = 255): ?string
    {
        if ($v === null || is_bool($v) || (is_string($v) && str_starts_with($v, '='))) {
            return null;
        }
        $t = trim((string) $v);

        return $t === '' || $t === 'False' ? null : mb_substr($t, 0, $maks);
    }

    /**
     * Urai sel surat: satu sel dapat memuat beberapa nomor/tanggal bertanda "- " dalam baris terpisah.
     *
     * @return array<int, array>
     */
    private function surat(string $jenis, mixed $nomor, mixed $tanggal, int $line, array &$peringatan, array $tambahan = []): array
    {
        $nomorList = $this->pecahBaris($nomor);
        if (! $nomorList) {
            return [];
        }
        $tglList = is_numeric($tanggal) ? [$this->tanggalExcel($tanggal)] : array_map(fn ($t) => $this->tanggalTeks($t), $this->pecahBaris($tanggal));
        if (count($tglList) > 0 && count($tglList) !== count($nomorList)) {
            $peringatan[] = "Baris {$line}: jumlah nomor surat {$jenis} (".count($nomorList).') berbeda dari jumlah tanggal ('.count($tglList).'); dipasangkan berurutan.';
        }

        $hasil = [];
        foreach ($nomorList as $i => $no) {
            $tgl = $tglList[$i] ?? null;
            $hasil[] = ['jenis' => $jenis, 'urutan' => $i + 1, 'nomor' => mb_substr($no, 0, 191), 'tanggal' => $tgl] + ($i === 0 ? $tambahan : []);
        }

        return $hasil;
    }

    /** @return array<int, string> */
    private function pecahBaris(mixed $v): array
    {
        if ($v === null || is_bool($v) || (is_string($v) && str_starts_with($v, '=')) || (is_string($v) && trim($v) === '')) {
            return [];
        }
        $out = [];
        foreach (preg_split('/\R/u', (string) $v) as $baris) {
            $baris = trim(preg_replace('/^\s*(?:[-•*]|\d+[.)])\s*/u', '', $baris));
            if ($baris !== '' && $baris !== 'False') {
                $out[] = $baris;
            }
        }

        return $out;
    }

    private function tanggalExcel(mixed $serial): ?string
    {
        try {
            return $serial > 20000 ? ExcelDate::excelToDateTimeObject((float) $serial)->format('Y-m-d') : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Terima "13 Oktober 2025", "12/08/2022", atau "2026-08-05". */
    private function tanggalTeks(string $t): ?string
    {
        $t = mb_strtolower(trim($t));
        if (preg_match('/^(\d{1,2})\s+([a-z]+)\s+(\d{4})$/', $t, $m) && isset(self::BULAN[$m[2]])) {
            return checkdate(self::BULAN[$m[2]], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], self::BULAN[$m[2]], $m[1]) : null;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $t, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]) : null;
        }
        try {
            return preg_match('/^\d{4}-\d{2}-\d{2}/', $t) ? Carbon::parse($t)->format('Y-m-d') : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function ringkas(array $baris, array $peringatan): array
    {
        $koleksi = collect($baris);
        $tidakCocok = $koleksi->whereNull('ref_instansi_id')->pluck('nama_instansi')->unique()->values();
        $ada = RekomendasiJf::get(['nama_instansi_normal', 'jabatan_pokok'])->map(fn ($r) => $r->nama_instansi_normal.'|'.$r->jabatan_pokok)->flip();

        return [
            'baris' => $koleksi->count(),
            'instansi' => $koleksi->pluck('nama_instansi_normal')->unique()->count(),
            'baru' => $koleksi->filter(fn ($b) => ! isset($ada[$b['nama_instansi_normal'].'|'.$b['jabatan_pokok']]))->count(),
            'diperbarui' => $koleksi->filter(fn ($b) => isset($ada[$b['nama_instansi_normal'].'|'.$b['jabatan_pokok']]))->count(),
            'per_jabatan' => $koleksi->countBy('jabatan_pokok')->all(),
            'per_status_rekom' => $koleksi->countBy(fn ($b) => $b['status_rekom'] ?? '(kosong)')->all(),
            'instansi_tidak_cocok' => $tidakCocok->take(100)->all(),
            'jumlah_tidak_cocok' => $tidakCocok->count(),
            'jumlah_surat' => $koleksi->sum(fn ($b) => count($b['surat'])),
            'jumlah_peringatan' => count($peringatan),
        ];
    }
}
