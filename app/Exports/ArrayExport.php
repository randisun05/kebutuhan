<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/** Export sederhana dari array (template impor, rekap). */
class ArrayExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private array $headings, private array $rows) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }
}
