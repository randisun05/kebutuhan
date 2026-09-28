<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MonitoringExport implements FromCollection, ShouldAutoSize, WithHeadings
{
    private const STATUS = [
        'kurang' => 'Kurang', 'lebih' => 'Lebih (gemuk)', 'sesuai' => 'Sesuai',
        'kosong' => 'Kosong', 'tanpa_abk' => 'Belum ada ABK',
    ];

    public function __construct(private Collection $positions) {}

    public function headings(): array
    {
        return ['No', 'Instansi', 'Unit Kerja', 'Jabatan', 'Jenis Jabatan', 'Kebutuhan (ABK)', 'Existing',
            'Selisih', 'Status', '% Pemenuhan', 'Proyeksi Pensiun 5 Th', 'Formasi Ditetapkan'];
    }

    public function collection(): Collection
    {
        return $this->positions->values()->map(fn ($p, $i) => [
            $i + 1, $p['instansi_nama'], $p['unit_nama'], $p['jabatan_nama'], $p['jenis_label'],
            $p['kebutuhan'], $p['existing'], $p['selisih'], self::STATUS[$p['status']] ?? $p['status'],
            $p['persentase'], $p['pensiun'], $p['formasi'],
        ]);
    }
}
