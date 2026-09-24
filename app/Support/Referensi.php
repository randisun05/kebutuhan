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
