<?php

namespace App\Support;

/**
 * Referensi statis yang dipakai bersama backend & frontend.
 */
class Referensi
{
    public const JENIS_JABATAN = [
        'jpt_utama' => 'JPT Utama',
        'jpt_madya' => 'JPT Madya',
        'jpt_pratama' => 'JPT Pratama',
        'administrator' => 'Administrator',
        'pengawas' => 'Pengawas',
        'pelaksana' => 'Pelaksana',
        'fungsional' => 'Fungsional',
    ];

    public const JENIS_INSTANSI = [
        'pusat' => 'Instansi Pusat',
        'provinsi' => 'Pemerintah Provinsi',
        'kabupaten' => 'Pemerintah Kabupaten',
        'kota' => 'Pemerintah Kota',
    ];

    public const ESELON = ['I', 'II', 'III', 'IV', 'non'];

    /** Waktu kerja efektif (menit/tahun) sesuai pedoman ABK: 1.250 jam. */
    public const WAKTU_KERJA_EFEKTIF = 75000;

    /**
     * Konversi satuan periode volume beban kerja ke setahun, diturunkan dari jam kerja efektif
     * PermenPANRB 1/2020: 1.250 jam/tahun = 104 jam (6.250 menit)/bulan = 25 jam (1.500 menit)/minggu
     * = 5 jam (300 menit)/hari.
     */
    public const PERIODE_PER_TAHUN = ['tahun' => 1, 'bulan' => 12, 'minggu' => 50, 'hari' => 250];

    public const PERIODE_LABEL = ['tahun' => 'per tahun', 'bulan' => 'per bulan', 'minggu' => 'per minggu', 'hari' => 'per hari'];

    public const PRIORITAS = [1 => 'Tinggi', 2 => 'Sedang', 3 => 'Rendah'];

    /**
     * Unsur informasi jabatan (uraian jabatan) mengikuti format Anjab PermenPANRB 1/2020.
     * tipe "list" = daftar baris teks, "text" = paragraf.
     */
    public const INFORMASI_JABATAN = [
        'kualifikasi_pendidikan' => ['label' => 'Kualifikasi: pendidikan formal', 'tipe' => 'list'],
        'kualifikasi_diklat' => ['label' => 'Kualifikasi: pendidikan & pelatihan', 'tipe' => 'list'],
        'kualifikasi_pengalaman' => ['label' => 'Kualifikasi: pengalaman kerja', 'tipe' => 'list'],
        'bahan_kerja' => ['label' => 'Bahan kerja', 'tipe' => 'list'],
        'perangkat_kerja' => ['label' => 'Perangkat / alat kerja', 'tipe' => 'list'],
        'tanggung_jawab' => ['label' => 'Tanggung jawab', 'tipe' => 'list'],
        'wewenang' => ['label' => 'Wewenang', 'tipe' => 'list'],
        'korelasi_jabatan' => ['label' => 'Korelasi jabatan (jabatan – unit – dalam hal)', 'tipe' => 'list'],
        'kondisi_lingkungan' => ['label' => 'Kondisi lingkungan kerja', 'tipe' => 'list'],
        'risiko_bahaya' => ['label' => 'Risiko bahaya', 'tipe' => 'list'],
        'syarat_keterampilan' => ['label' => 'Syarat jabatan: keterampilan kerja', 'tipe' => 'list'],
        'syarat_bakat' => ['label' => 'Syarat jabatan: bakat kerja', 'tipe' => 'list'],
        'syarat_temperamen' => ['label' => 'Syarat jabatan: temperamen kerja', 'tipe' => 'list'],
        'syarat_minat' => ['label' => 'Syarat jabatan: minat kerja', 'tipe' => 'list'],
        'syarat_upaya_fisik' => ['label' => 'Syarat jabatan: upaya fisik', 'tipe' => 'list'],
        'syarat_kondisi_fisik' => ['label' => 'Syarat jabatan: kondisi fisik', 'tipe' => 'list'],
        'syarat_fungsi_pekerjaan' => ['label' => 'Syarat jabatan: fungsi pekerjaan', 'tipe' => 'list'],
        'prestasi_diharapkan' => ['label' => 'Prestasi kerja yang diharapkan', 'tipe' => 'text'],
    ];

    /** Horizon proyeksi pensiun mengikuti siklus perencanaan kebutuhan 5 tahun. */
    public const HORIZON_PROYEKSI_TAHUN = 5;

    /**
     * Prestasi Efektivitas Jabatan (PEJ) berdasarkan nilai Efektivitas Jabatan (EJ).
     */
    public static function pej(?float $ej): ?array
    {
        if ($ej === null) {
            return null;
        }

        return match (true) {
            $ej > 1.00 => ['nilai' => 'A', 'label' => 'Sangat Baik'],
            $ej >= 0.90 => ['nilai' => 'B', 'label' => 'Baik'],
            $ej >= 0.70 => ['nilai' => 'C', 'label' => 'Cukup'],
            $ej >= 0.50 => ['nilai' => 'D', 'label' => 'Sedang'],
            default => ['nilai' => 'E', 'label' => 'Kurang'],
        };
    }

    public static function options(array $map): array
    {
        return collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
