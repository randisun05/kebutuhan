<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case VerifikatorBkn = 'verifikator_bkn';
    case ValidatorKemenpan = 'validator_kemenpan';
    case OperatorInstansi = 'operator_instansi';
    case Pimpinan = 'pimpinan';
    case Pembina = 'pembina';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::VerifikatorBkn => 'Verifikator BKN',
            self::ValidatorKemenpan => 'Validator KemenPANRB',
            self::OperatorInstansi => 'Operator Instansi',
            self::Pimpinan => 'Pimpinan (Monitoring)',
            self::Pembina => 'Operator Instansi Pembina',
        };
    }

    public static function options(): array
    {
        return array_map(fn (self $r) => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
