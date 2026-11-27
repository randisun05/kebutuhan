<?php

namespace App\Services;

use App\Models\ImporProfil;
use App\Models\InstansiAlias;
use App\Models\JfJabatanPokok;
use App\Models\JfJenjang;
use App\Models\KebutuhanFakta;
use App\Models\RefInstansiBkn;
use App\Models\RekomendasiJf;
use App\Models\RekomendasiJfImpor;
use App\Models\SumberData;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Impor Excel rekap kebutuhan JF MASN. Tata letak berkas (sheet, kolom, blok angka) dibaca dari profil impor,
 * sedangkan jabatan pokok, jenjang, dan sumber angka dibaca dari master di database, sehingga perubahan tata letak
 * atau penambahan JF/jenjang/sumber cukup dilakukan lewat menu manajemen. Angka disimpan sebagai fakta berversi.
 * Lowongan tidak disimpan: selalu bezetting − rekomendasi.
 */
class RekomendasiJfImportService
{
    private const BULAN = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7,
        'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
    ];

    /** Lebar blok angka bawaan (jumlah kolom jenjang) bila profil tidak menyebutkan. */
    private const LEBAR_BLOK = 4;

    /**
     * Baca dan validasi berkas tanpa menulis ke database.
     *
     * @return array{baris: array, peringatan: array, ringkas: array, profil_id: int}
     */
    public function baca(string $path, ?ImporProfil $profil = null): array
    {
        $profil ??= ImporProfil::where('is_active', true)->orderBy('id')->first();
        if (! $profil) {
            throw new \RuntimeException('Belum ada profil impor aktif. Tambahkan di menu Profil Impor.');
        }
        $p = $profil->pemetaan;

        $reader = IOFactory::createReaderForFile($path);
        // Mode data saja (tanpa gaya sel) jauh lebih cepat (sekitar 3 dtk, bukan 40 dtk) dan tetap memuat nilai tersimpan
        // sel rumus; diuji sama persis pada berkas sumber. Banyak kolom isian berisi VLOOKUP dari sheet lain,
        // dan nilai tersimpannya adalah datanya (dibaca lewat getOldCalculatedValue).
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$profil->sheet]);
        try {
            $sheet = $reader->load($path)->getSheetByName($profil->sheet);
        } catch (Throwable $e) {
            throw new \RuntimeException('Berkas tidak dapat dibaca atau tidak memuat sheet "'.$profil->sheet.'".', 0, $e);
        }
        if (! $sheet) {
            throw new \RuntimeException('Sheet "'.$profil->sheet.'" tidak ditemukan pada berkas.');
        }

        $master = $this->muatMaster();
        $col = fn (?string $huruf): ?int => $huruf ? Coordinate::columnIndexFromString(strtoupper($huruf)) - 1 : null;
        $K = $p['kolom'] ?? [];
        $cInstansi = $col($K['instansi'] ?? null);
        $cJabatan = $col($K['jabatan'] ?? null);
        if ($cInstansi === null || $cJabatan === null) {
            throw new \RuntimeException('Profil impor harus menentukan kolom instansi dan jabatan.');
        }

        $peringatan = [];
        $baris = [];
        $kunciTerlihat = [];
        $jabatanTakDikenal = [];
        $blokTakDikenal = [];
        $rumusTanpaNilai = 0;
        $cocok = $this->pencocok();

        foreach ($this->barisMentah($sheet, $rumusTanpaNilai) as $i => $r) {
            $nama = trim((string) ($r[$cInstansi] ?? ''));
            $namaJabatan = trim((string) ($r[$cJabatan] ?? ''));
            if ($nama === '' || $namaJabatan === '' || is_numeric($nama) || is_numeric($namaJabatan)
                || in_array(mb_strtolower($nama), ['nama instansi', 'instansi'], true) || mb_strtolower($namaJabatan) === 'jabatan') {
                continue; // baris judul, nomor kolom, atau kosong
            }
            $line = $i + 1;
            $jab = $master['jabatan'][mb_strtolower($namaJabatan)] ?? null;
            if (! $jab) {
                $jabatanTakDikenal[$namaJabatan] = ($jabatanTakDikenal[$namaJabatan] ?? 0) + 1;

                continue;
            }

            $normal = InstansiAlias::normalisasi($nama);
            $kunci = $normal.'|'.$jab['id'];
            if (isset($kunciTerlihat[$kunci])) {
                $peringatan[] = "Baris {$line}: {$nama} / {$jab['nama']} muncul lagi (sebelumnya baris {$kunciTerlihat[$kunci]}); baris ini yang dipakai.";
            }
            $kunciTerlihat[$kunci] = $line;

            // angka per blok sumber → fakta per jenjang
            $fakta = [];
            $nilaiBlok = [];
            foreach ($p['blok'] ?? [] as $blok) {
                $sumber = $master['sumber'][$blok['sumber'] ?? ''] ?? null;
                if (! $sumber || $sumber['adapter'] !== 'fakta') {
                    $blokTakDikenal[$blok['sumber'] ?? '(kosong)'] = true;

                    continue;
                }
                $lebar = (int) ($blok['lebar'] ?? self::LEBAR_BLOK);
                $angka = $this->angkaBlok($r, $col($blok['kolom_awal'] ?? null), $lebar, $line, $sumber['nama'], $peringatan);
                if (! array_filter($angka, fn ($v) => $v !== null) && ! empty($blok['cadangan_kolom_awal'])) {
                    $angka = $this->angkaBlok($r, $col($blok['cadangan_kolom_awal']), $lebar, $line, $sumber['nama'].' (cadangan)', $peringatan);
                }
                $nilaiBlok[$sumber['kode']] = $angka;
                foreach ($angka as $slot => $nilai) {
                    $jenjang = $jab['jenjang_per_slot'][$slot + 1] ?? null;
                    if ($jenjang === null) {
                        if (($nilai ?? 0) > 0) {
                            $peringatan[] = "Baris {$line}: {$nama} / {$jab['nama']} berisi angka {$sumber['nama']} pada jenjang ke-".($slot + 1).", padahal {$jab['nama']} tidak punya jenjang itu (diabaikan).";
                        }

                        continue;
                    }
                    if ($nilai !== null) {
                        $fakta[] = ['sumber' => $sumber['kode'], 'jenjang' => $jenjang, 'nilai' => $nilai];
                    }
                }
            }

            $surat = [];
            foreach ($p['surat'] ?? [] as $def) {
                $tambahan = [];
                if (! empty($def['tautan'])) {
                    $tambahan['tautan'] = $this->teks($r[$col($def['tautan'])] ?? null);
                }
                if (! empty($def['ttd_jabatan'])) {
                    $tambahan['penandatangan_jabatan'] = $this->teks($r[$col($def['ttd_jabatan'])] ?? null);
                }
                if (! empty($def['ttd_nama'])) {
                    $tambahan['penandatangan_nama'] = $this->teks($r[$col($def['ttd_nama'])] ?? null);
                }
                $surat = array_merge($surat, $this->surat((string) $def['jenis'], $r[$col($def['nomor'] ?? null)] ?? null,
                    isset($def['tanggal']) ? ($r[$col($def['tanggal'])] ?? null) : null, $line, $peringatan, $tambahan));
            }

            $adaMenpan = collect($fakta)->contains(fn ($f) => $f['sumber'] === 'penetapan_menpan' && ($f['nilai'] ?? 0) > 0) || collect($surat)->contains('jenis', 'penetapan_menpan');
            $sesuai = $this->teks($r[$col($K['sesuai_bezetting'] ?? null)] ?? null);
            $catatan = collect((array) ($K['catatan'] ?? []))->map(fn ($h) => $this->teks($r[$col($h)] ?? null))->filter()->implode("\n");
            $v = fn (string $kunciKolom, int $maks = 255) => isset($K[$kunciKolom]) ? $this->teks($r[$col($K[$kunciKolom])] ?? null, $maks) : null;

            $baris[] = [
                'nama_instansi' => $nama, 'nama_normal' => $normal, 'alias_id' => $cocok['alias'][$normal] ?? null, 'ref_instansi_id' => $cocok['ref']($normal),
                'jabatan_pokok_id' => $jab['id'], 'jabatan_nama' => $jab['nama'],
                'jenis_instansi' => $v('jenis_instansi', 16), 'pokja' => $v('pokja', 16), 'wilayah_kerja' => $v('wilayah_kerja'),
                'status_rekom' => $v('status_rekom', 40), 'jenis_rekom' => $v('jenis_rekom', 24),
                'status_menpan' => $adaMenpan ? 'ditetapkan' : null,
                'ditetapkan_sesuai_bezetting' => $sesuai === null ? null : in_array(mb_strtolower($sesuai), ['ya', 'sesuai', 'true'], true),
                'catatan' => $catatan ?: null, 'fakta' => $fakta, 'surat' => $surat,
            ];
        }

        foreach ($jabatanTakDikenal as $nama => $n) {
            $peringatan[] = "{$n} baris berjabatan \"{$nama}\" dilewati karena belum ada di master Jabatan Pokok JF.";
        }
        foreach (array_keys($blokTakDikenal) as $kode) {
            $peringatan[] = "Blok angka untuk sumber \"{$kode}\" dilewati karena sumber itu tidak ada, nonaktif, atau bukan sumber impor (menu Sumber Data).";
        }
        if ($rumusTanpaNilai > 0) {
            $peringatan[] = "{$rumusTanpaNilai} sel rumus tidak memiliki nilai tersimpan (berkas belum dihitung ulang di Excel) dan dianggap kosong. Buka dan simpan ulang berkas di Excel, lalu impor lagi.";
        }

        return ['baris' => $baris, 'peringatan' => $peringatan, 'ringkas' => $this->ringkas($baris, $peringatan, $master), 'profil_id' => $profil->id];
    }

    /** Simpan hasil baca ke database. Baris yang sudah ada diperbarui; angka disimpan berversi (riwayat tetap ada). */
    public function simpan(array $hasil, ?User $user, string $namaBerkas): RekomendasiJfImpor
    {
        $sumberId = SumberData::pluck('id', 'kode');
        $jenjangId = JfJenjang::pluck('id', 'kode');

        return DB::transaction(function () use ($hasil, $user, $namaBerkas, $sumberId, $jenjangId) {
            $impor = RekomendasiJfImpor::create([
                'user_id' => $user?->id, 'impor_profil_id' => $hasil['profil_id'] ?? null, 'nama_berkas' => mb_substr($namaBerkas, 0, 255),
                'jumlah_baris' => count($hasil['baris']), 'peringatan' => array_slice($hasil['peringatan'], 0, 300),
            ]);
            $dibuat = $diperbarui = $faktaBaru = 0;

            foreach ($hasil['baris'] as $b) {
                $alias = InstansiAlias::firstOrNew(['nama_normal' => $b['nama_normal']]);
                if (! $alias->exists) {
                    $alias->fill(['nama' => $b['nama_instansi'], 'ref_instansi_id' => $b['ref_instansi_id'], 'asal' => 'impor']);
                }
                // atribut opsional diperbarui bila sumber mengisinya; pemetaan ke referensi (mungkin manual) tidak ditimpa
                foreach (['jenis_instansi', 'pokja', 'wilayah_kerja'] as $f) {
                    if ($b[$f] !== null) {
                        $alias->{$f} = $b[$f];
                    }
                }
                $alias->save();

                $rekom = RekomendasiJf::firstOrNew(['alias_id' => $alias->id, 'jabatan_pokok_id' => $b['jabatan_pokok_id']]);
                $baru = ! $rekom->exists;
                $rekom->fill(collect($b)->only(['status_rekom', 'jenis_rekom', 'status_menpan', 'ditetapkan_sesuai_bezetting', 'catatan'])->all() + ['impor_id' => $impor->id])->save();
                $baru ? $dibuat++ : $diperbarui++;

                foreach ($b['fakta'] as $f) {
                    $faktaBaru += $this->simpanFakta($rekom, $sumberId[$f['sumber']], $jenjangId[$f['jenjang']], $f['nilai'], $impor->id);
                }

                $rekom->surats()->delete();
                foreach ($b['surat'] as $s) {
                    $rekom->surats()->create($s);
                }
            }

            $impor->update(['dibuat' => $dibuat, 'diperbarui' => $diperbarui, 'fakta_baru' => $faktaBaru]);

            return $impor;
        });
    }

    /** Tambah versi baru hanya bila nilainya berubah; nilai lama ditandai bukan terbaru. @return int jumlah baris fakta baru */
    private function simpanFakta(RekomendasiJf $rekom, int $sumberId, int $jenjangId, int $nilai, int $imporId): int
    {
        $kunci = ['rekomendasi_jf_id' => $rekom->id, 'sumber_id' => $sumberId, 'jenjang_id' => $jenjangId, 'unit_kerja_id' => null];
        $terbaru = KebutuhanFakta::where($kunci)->where('is_terbaru', true)->first();
        if ($terbaru && $terbaru->nilai === $nilai) {
            return 0;
        }
        $terbaru?->update(['is_terbaru' => false]);
        KebutuhanFakta::create($kunci + ['nilai' => $nilai, 'impor_id' => $imporId, 'is_terbaru' => true]);

        return 1;
    }

    /** Master yang dipakai impor: jabatan (beserta jenjang per posisi kolom) dan sumber aktif. */
    private function muatMaster(): array
    {
        $jabatan = [];
        foreach (JfJabatanPokok::where('is_active', true)->with('jenjangs')->get() as $j) {
            $jabatan[mb_strtolower($j->nama)] = [
                'id' => $j->id, 'nama' => $j->nama,
                'jenjang_per_slot' => $j->jenjangs->where('is_active', true)->mapWithKeys(fn ($jj) => [(int) $jj->pivot->urutan_kolom => $jj->kode])->all(),
            ];
        }
        $sumber = SumberData::where('is_active', true)->get()->mapWithKeys(fn ($s) => [$s->kode => ['kode' => $s->kode, 'nama' => $s->nama, 'adapter' => $s->adapter]])->all();

        return ['jabatan' => $jabatan, 'sumber' => $sumber];
    }

    /** Pencocok nama instansi: alias yang sudah ada (termasuk pemetaan manual) dulu, lalu kecocokan nama referensi BKN. */
    private function pencocok(): array
    {
        $alias = InstansiAlias::pluck('id', 'nama_normal')->all();
        $aliasRef = InstansiAlias::whereNotNull('ref_instansi_id')->pluck('ref_instansi_id', 'nama_normal')->all();
        $ref = [];
        foreach (RefInstansiBkn::get(['id', 'nama']) as $r) {
            $n = RefInstansiBkn::normalisasiNama($r->nama);
            $ref[$n] ??= $r->id;
            $ref[preg_replace('/^pemerintah /', '', $n)] ??= $r->id;
        }

        return [
            'alias' => $alias,
            'ref' => fn (string $normal) => $aliasRef[$normal] ?? $ref[$normal] ?? $ref[preg_replace('/^pemerintah /', '', $normal)] ?? null,
        ];
    }

    /**
     * Baca seluruh sel sheet menjadi baris array (indeks kolom 0-based). Sel rumus memakai nilai tersimpan
     * (cached) terakhir; nilai galat Excel (#REF!, #VALUE!, ...) dianggap kosong.
     *
     * @return array<int, array<int, mixed>>
     */
    private function barisMentah(Worksheet $sheet, int &$rumusTanpaNilai): array
    {
        $hasil = [];
        foreach ($sheet->getRowIterator() as $row) {
            $iter = $row->getCellIterator();
            $iter->setIterateOnlyExistingCells(true);
            $data = [];
            foreach ($iter as $cell) {
                $nilai = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
                if ($cell->isFormula() && ($nilai === null || $nilai === '')) {
                    $rumusTanpaNilai++;
                }
                if (is_string($nilai) && preg_match('/^#(REF|VALUE|N\/A|DIV\/0|NAME|NUM|NULL)[!?]?$/', $nilai)) {
                    $nilai = null;
                }
                $data[Coordinate::columnIndexFromString($cell->getColumn()) - 1] = $nilai;
            }
            $hasil[$row->getRowIndex() - 1] = $data;
        }

        return $hasil;
    }

    /** @return array<int, ?int> angka sepanjang blok; sel rumus/kosong/teks menjadi null. */
    private function angkaBlok(array $r, ?int $awal, int $lebar, int $line, string $label, array &$peringatan): array
    {
        $hasil = [];
        for ($k = 0; $k < $lebar; $k++) {
            $v = $awal === null ? null : ($r[$awal + $k] ?? null);
            if ($v === null || $v === '' || is_bool($v) || (is_string($v) && str_starts_with($v, '='))) {
                $hasil[] = null;
            } elseif (is_numeric($v) && (float) $v == round((float) $v) && (float) $v >= 0) {
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
            $hasil[] = ['jenis' => $jenis, 'urutan' => $i + 1, 'nomor' => mb_substr($no, 0, 191), 'tanggal' => $tglList[$i] ?? null] + ($i === 0 ? $tambahan : []);
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

    private function ringkas(array $baris, array $peringatan, array $master): array
    {
        $k = collect($baris);
        $tidakCocok = $k->whereNull('ref_instansi_id')->pluck('nama_instansi')->unique()->values();
        $aliasAda = InstansiAlias::pluck('id', 'nama_normal');
        $ada = RekomendasiJf::get(['alias_id', 'jabatan_pokok_id'])->map(fn ($r) => $r->alias_id.'|'.$r->jabatan_pokok_id)->flip();
        $sudah = fn ($b) => isset($aliasAda[$b['nama_normal']]) && isset($ada[$aliasAda[$b['nama_normal']].'|'.$b['jabatan_pokok_id']]);

        return [
            'baris' => $k->count(),
            'instansi' => $k->pluck('nama_normal')->unique()->count(),
            'baru' => $k->reject($sudah)->count(),
            'diperbarui' => $k->filter($sudah)->count(),
            'per_jabatan' => $k->countBy('jabatan_nama')->all(),
            'per_status_rekom' => $k->countBy(fn ($b) => $b['status_rekom'] ?? '(kosong)')->all(),
            'angka_per_sumber' => $k->flatMap(fn ($b) => $b['fakta'])->groupBy('sumber')->map(fn ($g) => (int) $g->sum('nilai'))->all(),
            'instansi_tidak_cocok' => $tidakCocok->take(100)->all(),
            'jumlah_tidak_cocok' => $tidakCocok->count(),
            'jumlah_surat' => $k->sum(fn ($b) => count($b['surat'])),
            'jumlah_peringatan' => count($peringatan),
        ];
    }
}
