<?php

namespace App\Services\Ai;

use App\Models\AiRiwayat;
use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\Peringatan;
use App\Models\UnitKerja;
use App\Models\Usulan;
use App\Services\MonitoringService;
use App\Services\PeringatanService;
use App\Services\SaranUsulanService;
use App\Support\Referensi;
use Illuminate\Support\Str;

/**
 * Fitur AI SIMONKEB di atas GeminiClient. Hasil AI selalu berupa saran/draf yang
 * diperiksa pengguna; tidak ada data aplikasi yang diubah otomatis.
 *
 * Semua hasil disimpan di tabel ai_riwayat lewat riwayat(): permintaan berikutnya untuk subjek
 * yang sama memakai hasil tersimpan dan tidak memanggil Gemini lagi, kecuali pengguna meminta
 * "Buat ulang". Fitur AI baru WAJIB lewat riwayat() juga.
 */
class AsistenAi
{
    public const JENIS = [
        'anjab_draft' => 'Draf Anjab-ABK',
        'analisis_monitoring' => 'Analisis monitoring',
        'saran_peringatan' => 'Saran tindak lanjut peringatan',
        'justifikasi_usulan' => 'Justifikasi usulan kebutuhan',
    ];

    private const SISTEM = 'Anda asisten analis kepegawaian ASN Indonesia yang memahami UU 20/2023 tentang ASN, '
        .'PermenPANRB 1/2020 tentang Pedoman Analisis Jabatan dan Analisis Beban Kerja, dan perencanaan kebutuhan ASN. '
        .'Jawab dalam Bahasa Indonesia baku yang ringkas dan praktis. Jangan mengarang angka yang tidak diberikan.';

    public function __construct(private GeminiClient $gemini, private MonitoringService $monitoring) {}

    /**
     * Pintu tunggal pemanggilan AI. Mengembalikan hasil terakhir untuk (jenis, kunci) bila ada;
     * Gemini hanya dipanggil bila belum ada riwayat atau $baru = true. Bila prompt (data sumber)
     * sudah berbeda dari saat hasil dibuat, hasil tetap dipakai tetapi ditandai usang.
     *
     * @return array{hasil: string, riwayat: array}
     */
    public function riwayat(string $jenis, string $kunci, ?int $instansiId, string $prompt, array $opsi = [], bool $baru = false): array
    {
        $sidik = md5($prompt.json_encode($opsi));

        if (! $baru && ($lama = $this->terakhir($jenis, $kunci))) {
            return ['hasil' => $lama->hasil, 'riwayat' => $this->meta($lama, dariRiwayat: true, usang: $lama->sidik !== $sidik)];
        }

        $hasil = $this->gemini->generate($prompt, ['system' => self::SISTEM] + $opsi);
        if (! empty($opsi['json'])) {
            GeminiClient::bacaJson($hasil); // jawaban rusak tidak disimpan agar tidak terpakai ulang
        }

        $riwayat = AiRiwayat::create([
            'jenis' => $jenis,
            'kunci' => $kunci,
            'instansi_id' => $instansiId,
            'user_id' => auth()->id(),
            'sidik' => $sidik,
            'model' => config('gemini.model'),
            'prompt' => $prompt,
            'hasil' => $hasil,
        ]);

        return ['hasil' => $hasil, 'riwayat' => $this->meta($riwayat->load('user:id,name'), dariRiwayat: false, usang: false)];
    }

    public function terakhir(string $jenis, string $kunci): ?AiRiwayat
    {
        return AiRiwayat::with('user:id,name')->where('jenis', $jenis)->where('kunci', $kunci)->latest('id')->first();
    }

    /** Info riwayat untuk frontend: kapan, oleh siapa, dari riwayat atau baru, usang atau tidak. */
    private function meta(AiRiwayat $r, bool $dariRiwayat, bool $usang): array
    {
        return [
            'id' => $r->id,
            'dibuat' => $r->created_at?->toIso8601String(),
            'oleh' => $r->user?->name,
            'dari_riwayat' => $dariRiwayat,
            'usang' => $usang,
        ];
    }

    /**
     * Draf Anjab-ABK: ikhtisar, uraian tugas (dengan perkiraan volume & norma waktu), dan informasi jabatan.
     * Hasil sudah dibersihkan agar sesuai aturan validasi form ABK.
     */
    public function draftAnjab(Jabatan $jabatan, ?UnitKerja $unit, ?string $catatan = null, bool $baru = false): array
    {
        $informasi = collect(Referensi::INFORMASI_JABATAN);
        $periode = implode(', ', array_keys(Referensi::PERIODE_PER_TAHUN));
        $catatan = $catatan ? Str::limit(trim($catatan), 500, '') : null;

        $prompt = "Susun draf Analisis Jabatan dan Analisis Beban Kerja sesuai PermenPANRB 1/2020 untuk:\n"
            ."- Jabatan: {$jabatan->nama} (jenis: ".(Referensi::JENIS_JABATAN[$jabatan->jenis] ?? $jabatan->jenis)
            .($jabatan->kelas_jabatan ? ", kelas {$jabatan->kelas_jabatan}" : '').")\n"
            .($unit ? "- Unit kerja: {$unit->nama}".($unit->instansi ? ' pada '.$unit->instansi->nama : '')."\n" : '')
            .($jabatan->kualifikasi_pendidikan ? "- Kualifikasi pendidikan: {$jabatan->kualifikasi_pendidikan}\n" : '')
            .($catatan ? "- Catatan dari pengguna: {$catatan}\n" : '')
            ."\nKetentuan:\n"
            ."- 6 sampai 12 uraian tugas, diawali kata kerja (Menyusun, Melaksanakan, Memverifikasi, …).\n"
            ."- hasil_kerja berupa satuan hasil (Dokumen, Laporan, Berkas, Kegiatan, …).\n"
            ."- satuan_periode salah satu dari: {$periode}; volume bilangan bulat per periode tersebut.\n"
            .'- norma_waktu dalam menit per satu hasil kerja, wajar untuk ASN. Waktu kerja efektif '
            .number_format(Referensi::WAKTU_KERJA_EFEKTIF, 0, ',', '.')." menit/tahun.\n"
            ."- Isi setiap unsur informasi jabatan dengan 2 sampai 5 butir singkat; prestasi_diharapkan satu paragraf.\n";

        $properti = $informasi->map(fn ($m) => $m['tipe'] === 'list'
            ? ['type' => 'ARRAY', 'items' => ['type' => 'STRING']]
            : ['type' => 'STRING'])->all();

        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'ikhtisar_jabatan' => ['type' => 'STRING'],
                'uraian_tugas' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'uraian_tugas' => ['type' => 'STRING'],
                        'hasil_kerja' => ['type' => 'STRING'],
                        'volume' => ['type' => 'INTEGER'],
                        'satuan_periode' => ['type' => 'STRING', 'enum' => array_keys(Referensi::PERIODE_PER_TAHUN)],
                        'norma_waktu' => ['type' => 'INTEGER'],
                    ],
                    'required' => ['uraian_tugas', 'hasil_kerja', 'volume', 'satuan_periode', 'norma_waktu'],
                ]],
                'informasi' => ['type' => 'OBJECT', 'properties' => $properti],
            ],
            'required' => ['ikhtisar_jabatan', 'uraian_tugas', 'informasi'],
        ];

        $kunci = "jabatan:{$jabatan->id}|unit:".($unit->id ?? '-').($catatan ? '|catatan:'.md5(mb_strtolower($catatan)) : '');
        $riwayat = $this->riwayat('anjab_draft', $kunci, $unit?->instansi_id, $prompt,
            ['temperature' => 0.5, 'json' => true, 'schema' => $schema], $baru);
        $data = GeminiClient::bacaJson($riwayat['hasil']);

        $uraian = collect($data['uraian_tugas'] ?? [])
            ->filter(fn ($r) => is_array($r) && trim((string) ($r['uraian_tugas'] ?? '')) !== '')
            ->take(20)
            ->map(fn ($r) => [
                'uraian_tugas' => Str::limit(trim((string) $r['uraian_tugas']), 1000, ''),
                'hasil_kerja' => Str::limit(trim((string) ($r['hasil_kerja'] ?? '')), 255, ''),
                'volume' => max(0, min(100000, (int) ($r['volume'] ?? 0))),
                'satuan_periode' => array_key_exists($r['satuan_periode'] ?? '', Referensi::PERIODE_PER_TAHUN) ? $r['satuan_periode'] : 'tahun',
                'norma_waktu' => max(0, min(75000, (int) ($r['norma_waktu'] ?? 0))),
            ])->values()->all();

        if (! $uraian) {
            throw new AiException('AI tidak menghasilkan uraian tugas. Silakan buat ulang atau tambahkan catatan.');
        }

        $info = $informasi->map(function ($m, $key) use ($data) {
            $nilai = $data['informasi'][$key] ?? null;

            return $m['tipe'] === 'list'
                ? collect((array) $nilai)->map(fn ($v) => Str::limit(trim((string) $v), 1000, ''))->filter()->take(10)->values()->all()
                : Str::limit(trim(is_array($nilai) ? implode(' ', $nilai) : (string) $nilai), 5000, '');
        })->all();

        return [
            'ikhtisar_jabatan' => Str::limit(trim((string) ($data['ikhtisar_jabatan'] ?? '')), 2000, ''),
            'uraian' => $uraian,
            'informasi' => $info,
            'riwayat' => $riwayat['riwayat'],
        ];
    }

    public static function kunciMonitoring(int $instansiId, array $filters): string
    {
        return "instansi:{$instansiId}|jenis:".($filters['jenis'] ?? '-').'|q:'.mb_strtolower($filters['q'] ?? '-');
    }

    /** Analisis terakhir yang tersimpan (tanpa memanggil AI), untuk ditampilkan saat halaman dibuka. */
    public function analisisMonitoringTersimpan(Instansi $instansi, array $filters = []): ?array
    {
        $r = $this->terakhir('analisis_monitoring', self::kunciMonitoring($instansi->id, $filters));

        return $r ? ['html' => $this->html($r->hasil), 'riwayat' => $this->meta($r, dariRiwayat: true, usang: false)] : null;
    }

    /** Analisis naratif kondisi kebutuhan vs existing satu instansi (HTML aman dari markdown). */
    public function analisisMonitoring(Instansi $instansi, array $filters = [], bool $baru = false): array
    {
        $kunci = self::kunciMonitoring($instansi->id, $filters);
        $filters['instansi_id'] = $instansi->id;
        $s = $this->monitoring->summary($filters);
        $jenis = $this->monitoring->byJenisJabatan($filters)->filter(fn ($j) => $j['kebutuhan'] || $j['existing']);
        $units = $this->monitoring->byUnit($instansi->id, $filters)->map(fn ($u) => ['nama' => $u['nama']] + $u['own'])
            ->filter(fn ($u) => $u['kurang'] || $u['lebih']);
        $jabatan = $this->monitoring->byJabatan($filters)->sortByDesc(fn ($j) => $j['kurang'] + $j['lebih'])->take(10);

        $baris = fn ($r) => "kebutuhan {$r['kebutuhan']}, existing {$r['existing']}, kurang {$r['kurang']}, lebih {$r['lebih']}"
            .', pemenuhan '.($r['persentase'] ?? '-').'%';

        $prompt = "Data monitoring kebutuhan vs existing ASN {$instansi->nama} (posisi = unit × jabatan, kebutuhan dari ABK final):\n"
            ."Ringkasan: {$baris($s)}, jabatan kosong {$s['jabatan_kosong']}, posisi tanpa ABK {$s['tanpa_abk']}, "
            ."pensiun 5 tahun {$s['pensiun']}, formasi ditetapkan {$s['formasi']}.\n"
            ."\nPer jenis jabatan:\n".$jenis->map(fn ($j) => "- {$j['label']}: {$baris($j)}")->implode("\n")
            ."\n\nUnit dengan selisih terbesar:\n".$units->sortByDesc(fn ($u) => $u['kurang'] + $u['lebih'])->take(12)
                ->map(fn ($u) => "- {$u['nama']}: {$baris($u)}")->implode("\n")
            ."\n\nJabatan dengan selisih terbesar:\n".$jabatan->map(fn ($j) => "- {$j['jabatan_nama']} ({$j['jenis_label']}): {$baris($j)}")->implode("\n")
            ."\n\nBuat analisis dalam markdown dengan bagian: **Ringkasan kondisi**, **Masalah utama** (maks. 5 butir), "
            .'**Rekomendasi** (redistribusi dari unit gemuk, usulan formasi, antisipasi pensiun, pemutakhiran ABK; maks. 6 butir), '
            .'dan **Prioritas tindakan 3 bulan ke depan**. Maksimal 400 kata. Sebut nama unit/jabatan dan angka dari data di atas.';

        $r = $this->riwayat('analisis_monitoring', $kunci, $instansi->id, $prompt, [], $baru);

        return ['html' => $this->html($r['hasil']), 'riwayat' => $r['riwayat']];
    }

    /** Saran langkah tindak lanjut sebuah peringatan dini. */
    public function saranPeringatan(Peringatan $peringatan, bool $baru = false): array
    {
        $peringatan->loadMissing(['instansi:id,nama', 'unitKerja:id,nama', 'jabatan:id,nama,jenis']);

        $prompt = "Peringatan dini pada sistem monitoring kebutuhan ASN:\n"
            .'- Jenis: '.(PeringatanService::ATURAN[$peringatan->kode] ?? $peringatan->kode)." (tingkat {$peringatan->tingkat})\n"
            ."- Judul: {$peringatan->judul}\n- Pesan: {$peringatan->pesan}\n"
            .($peringatan->instansi ? "- Instansi: {$peringatan->instansi->nama}\n" : '')
            .($peringatan->unitKerja ? "- Unit kerja: {$peringatan->unitKerja->nama}\n" : '')
            .($peringatan->jabatan ? "- Jabatan: {$peringatan->jabatan->nama}\n" : '')
            .($peringatan->data ? '- Data: '.Str::limit(json_encode($peringatan->data, JSON_UNESCAPED_UNICODE), 800)."\n" : '')
            .'- Terdeteksi sejak: '.$peringatan->pertama_terdeteksi_at?->format('d-m-Y')."\n"
            ."\nBerikan dalam markdown: penyebab yang mungkin (maks. 3 butir), langkah tindak lanjut konkret untuk operator "
            .'instansi (3–5 butir bernomor, sebut menu SIMONKEB bila relevan: Data Pegawai, Manajemen ABK, Usulan Kebutuhan, '
            .'Redistribusi Pegawai, Pengisian Formasi, Integrasi SIASN), dan satu kalimat contoh catatan tindak lanjut. Maksimal 200 kata.';

        $r = $this->riwayat('saran_peringatan', "peringatan:{$peringatan->id}", $peringatan->instansi_id, $prompt, [], $baru);

        return ['html' => $this->html($r['hasil']), 'riwayat' => $r['riwayat']];
    }

    /** Draf justifikasi usulan per jabatan untuk surat pengantar (markdown + HTML). */
    public function justifikasiUsulan(Usulan $usulan, bool $baru = false): array
    {
        $usulan->loadMissing(['instansi:id,nama', 'details.unitKerja:id,nama', 'details.jabatan:id,nama,jenis']);
        $periksa = app(SaranUsulanService::class)->periksa($usulan);

        $baris = $usulan->details->sortBy('prioritas')->take(40)->map(function ($d) use ($periksa) {
            $p = $periksa['rincian'][$d->id] ?? [];
            $sumber = match ($p['sumber'] ?? null) {
                'abk' => 'ABK final', 'sementara' => 'kebutuhan sementara', default => 'belum ada dasar kebutuhan',
            };

            return "- {$d->jabatan?->nama} pada {$d->unitKerja?->nama}: diusulkan {$d->jumlah_usul}, prioritas "
                .(Referensi::PRIORITAS[$d->prioritas] ?? $d->prioritas).', kebutuhan '.($p['kebutuhan'] ?? $d->kebutuhan_abk)." ({$sumber}), "
                .'existing '.($p['existing'] ?? $d->existing).', pensiun 5 tahun '.($p['pensiun'] ?? $d->proyeksi_pensiun)
                .', formasi belum terisi '.($p['formasi'] ?? 0).', saran sistem '.($p['saran'] ?? '-')
                .($d->keterangan ? ", keterangan: {$d->keterangan}" : '');
        })->implode("\n");

        $prompt = "Usulan kebutuhan ASN ({$usulan->jenis_asn}) tahun {$usulan->tahun} dari {$usulan->instansi?->nama}, perihal: {$usulan->perihal}.\n"
            ."Rincian per jabatan:\n{$baris}\n\n"
            .'Susun justifikasi dalam markdown untuk lampiran surat pengantar: satu paragraf pengantar, lalu per jabatan (urut prioritas) '
            .'satu butir alasan singkat berbasis angka di atas (kekurangan, pensiun, beban kerja ABK). Bila jumlah diusulkan melebihi saran sistem '
            .'atau belum ada dasar kebutuhan, sebutkan secara jujur dan sarankan apa yang perlu dilengkapi. Maksimal 400 kata.';

        $r = $this->riwayat('justifikasi_usulan', "usulan:{$usulan->id}", $usulan->instansi_id, $prompt, [], $baru);

        return ['html' => $this->html($r['hasil']), 'teks' => $r['hasil'], 'riwayat' => $r['riwayat']];
    }

    private function html(string $markdown): string
    {
        return Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }
}
