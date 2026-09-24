<?php

namespace App\Enums;

enum UsulanStatus: string
{
    case Draft = 'draft';
    case Diajukan = 'diajukan';
    case VerifikasiBkn = 'verifikasi_bkn';
    case PertimbanganTeknis = 'pertimbangan_teknis';
    case ValidasiKemenpan = 'validasi_kemenpan';
    case Ditetapkan = 'ditetapkan';
    case Dikembalikan = 'dikembalikan';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Diajukan => 'Diajukan Instansi',
            self::VerifikasiBkn => 'Verifikasi BKN',
            self::PertimbanganTeknis => 'Pertimbangan Teknis BKN',
            self::ValidasiKemenpan => 'Validasi KemenPANRB',
            self::Ditetapkan => 'Ditetapkan',
            self::Dikembalikan => 'Dikembalikan (Perbaikan)',
            self::Ditolak => 'Ditolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Diajukan => 'info',
            self::VerifikasiBkn, self::ValidasiKemenpan => 'primary',
            self::PertimbanganTeknis => 'warning',
            self::Ditetapkan => 'success',
            self::Dikembalikan => 'orange',
            self::Ditolak => 'danger',
        };
    }

    /** Urutan tahapan untuk progress/stepper. */
    public function step(): int
    {
        return match ($this) {
            self::Draft, self::Dikembalikan => 1,
            self::Diajukan => 2,
            self::VerifikasiBkn => 3,
            self::PertimbanganTeknis => 4,
            self::ValidasiKemenpan => 5,
            self::Ditetapkan => 6,
            self::Ditolak => 0,
        };
    }

    /** Usulan masih dapat diubah oleh operator instansi. */
    public function editable(): bool
    {
        return in_array($this, [self::Draft, self::Dikembalikan], true);
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'color' => $s->color(),
        ], self::cases());
    }
}
