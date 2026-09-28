<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PegawaiTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return ['nip', 'nama', 'kode_unit', 'kode_jabatan', 'status_kepegawaian', 'golongan', 'pendidikan', 'tanggal_lahir', 'tmt_jabatan'];
    }

    public function array(): array
    {
        return [["'199001012015031001", 'Contoh Nama Pegawai', 'UK-001', 'JF-ASDM-1', 'pns', 'III/a', 'S1', '1990-01-01', '2020-01-01']];
    }
}
