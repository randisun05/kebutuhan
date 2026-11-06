<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pemeriksaan kualitas data pegawai existing per instansi. Hanya pegawai aktif yang diperiksa.
 * Setiap pemeriksaan memuat jumlah temuan dan contoh (maks. CONTOH) agar bisa langsung dibenahi.
 */
class KualitasDataService
{
    public const CONTOH = 15;

    /** @return array<int, array{kode: string, judul: string, saran: string, tingkat: string, jumlah: int, contoh: array}> */
    public function periksa(int $instansiId): array
    {
        $pegawai = DB::table('pegawais as p')
            ->join('unit_kerjas as u', 'u.id', '=', 'p.unit_kerja_id')
            ->join('jabatans as j', 'j.id', '=', 'p.jabatan_id')
            ->where('p.instansi_id', $instansiId)->where('p.is_active', true)
            ->get(['p.id', 'p.nip', 'p.nama', 'p.golongan', 'p.pendidikan', 'p.tanggal_lahir', 'p.tmt_pensiun', 'p.sumber', 'p.siasn_synced_at',
                'p.status_kepegawaian', 'u.nama as unit', 'u.is_active as unit_aktif', 'j.nama as jabatan', 'j.is_active as jabatan_aktif']);

        $lahirMin = now()->subYears(17)->toDateString();
        $lahirMax = now()->subYears(75)->toDateString();
        $hariIni = now()->toDateString();

        $cek = [
            ['nip_tidak_valid', 'NIP tidak valid', 'Perbaiki NIP menjadi 18 digit angka.', 'tinggi',
                fn ($p) => ! preg_match('/^\d{18}$/', (string) $p->nip), fn ($p) => 'NIP "'.$p->nip.'"'],
            ['tanggal_lahir_kosong', 'Tanggal lahir kosong', 'Isi tanggal lahir agar TMT pensiun dan proyeksi dapat dihitung.', 'tinggi',
                fn ($p) => empty($p->tanggal_lahir), fn ($p) => 'Proyeksi pensiun tidak terhitung'],
            ['umur_tidak_wajar', 'Tanggal lahir tidak wajar', 'Periksa tanggal lahir (umur di bawah 17 atau di atas 75 tahun).', 'tinggi',
                fn ($p) => ! empty($p->tanggal_lahir) && ($p->tanggal_lahir > $lahirMin || $p->tanggal_lahir < $lahirMax), fn ($p) => 'Lahir '.substr((string) $p->tanggal_lahir, 0, 10)],
            ['lewat_pensiun', 'Masih aktif padahal sudah melewati TMT pensiun', 'Nonaktifkan pegawai yang sudah pensiun atau koreksi tanggal lahir/BUP.', 'tinggi',
                fn ($p) => ! empty($p->tmt_pensiun) && $p->tmt_pensiun < $hariIni, fn ($p) => 'TMT pensiun '.substr((string) $p->tmt_pensiun, 0, 10)],
            ['unit_nonaktif', 'Ditempatkan di unit kerja nonaktif', 'Pindahkan pegawai ke unit aktif atau aktifkan kembali unitnya.', 'sedang',
                fn ($p) => ! $p->unit_aktif, fn ($p) => 'Unit '.$p->unit],
            ['jabatan_nonaktif', 'Memakai jabatan nonaktif', 'Ganti ke jabatan aktif atau aktifkan kembali jabatannya.', 'sedang',
                fn ($p) => ! $p->jabatan_aktif, fn ($p) => 'Jabatan '.$p->jabatan],
            ['golongan_kosong', 'Golongan atau pendidikan kosong', 'Lengkapi golongan dan pendidikan (dipakai laporan komposisi).', 'rendah',
                fn ($p) => empty($p->golongan) || empty($p->pendidikan), fn ($p) => 'Golongan '.($p->golongan ?: '-').', pendidikan '.($p->pendidikan ?: '-')],
        ];

        if (config('siasn.enabled')) {
            $batas = now()->subMonths((int) config('simonkeb.peringatan.data_usang_bulan', 6))->toDateTimeString();
            $cek[] = ['belum_sinkron_siasn', 'PNS belum pernah/lama tidak disinkron dari SIASN', 'Jalankan sinkron data pegawai di menu Integrasi SIASN.', 'rendah',
                fn ($p) => $p->status_kepegawaian === 'pns' && (empty($p->siasn_synced_at) || $p->siasn_synced_at < $batas),
                fn ($p) => $p->siasn_synced_at ? 'Terakhir '.substr((string) $p->siasn_synced_at, 0, 10) : 'Belum pernah'];
        }

        $hasil = collect($cek)->map(function ($c) use ($pegawai) {
            [$kode, $judul, $saran, $tingkat, $uji, $ket] = $c;
            $temuan = $pegawai->filter($uji)->values();

            return [
                'kode' => $kode, 'judul' => $judul, 'saran' => $saran, 'tingkat' => $tingkat, 'jumlah' => $temuan->count(),
                'contoh' => $temuan->take(self::CONTOH)->map(fn ($p) => [
                    'id' => $p->id, 'nip' => $p->nip, 'nama' => $p->nama, 'unit' => $p->unit, 'keterangan' => $ket($p),
                ])->all(),
            ];
        });

        // NIP ganda tidak mungkin (unik), tetapi nama + tanggal lahir yang sama patut dicurigai sebagai data ganda
        $hasil->push($this->kemungkinanGanda($pegawai));

        return $hasil->values()->all();
    }

    private function kemungkinanGanda(Collection $pegawai): array
    {
        $grup = $pegawai->filter(fn ($p) => ! empty($p->tanggal_lahir))
            ->groupBy(fn ($p) => mb_strtolower(trim($p->nama)).'|'.substr((string) $p->tanggal_lahir, 0, 10))
            ->filter(fn ($g) => $g->count() > 1);
        $temuan = $grup->flatten(1)->values();

        return [
            'kode' => 'kemungkinan_ganda', 'judul' => 'Kemungkinan data ganda (nama dan tanggal lahir sama)',
            'saran' => 'Periksa apakah satu orang tercatat dengan dua NIP; nonaktifkan salah satunya.', 'tingkat' => 'sedang',
            'jumlah' => $temuan->count(),
            'contoh' => $temuan->take(self::CONTOH)->map(fn ($p) => [
                'id' => $p->id, 'nip' => $p->nip, 'nama' => $p->nama, 'unit' => $p->unit, 'keterangan' => 'Lahir '.substr((string) $p->tanggal_lahir, 0, 10),
            ])->all(),
        ];
    }
}
