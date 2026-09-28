<?php

namespace App\Imports;

use App\Models\AnjabAbk;
use App\Models\Jabatan;
use App\Models\UnitKerja;
use App\Support\Referensi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Impor uraian tugas ABK. Satu baris = satu uraian tugas; baris dengan
 * kode_unit + kode_jabatan + tahun yang sama membentuk satu ABK.
 * ABK draft yang sudah ada diganti uraian tugasnya; ABK final tidak ditimpa.
 */
class AbkImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $rows = 0;

    public array $errors = [];

    public function __construct(private int $instansiId, private int $userId) {}

    public function collection(Collection $rows): void
    {
        $units = UnitKerja::where('instansi_id', $this->instansiId)->whereNotNull('kode')->pluck('id', 'kode');
        $jabatans = Jabatan::pluck('id', 'kode');
        $groups = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $unitId = $units[trim((string) ($row['kode_unit'] ?? ''))] ?? null;
            $jabatanId = $jabatans[trim((string) ($row['kode_jabatan'] ?? ''))] ?? null;
            $tahun = (int) ($row['tahun'] ?? 0);
            $periode = strtolower(trim((string) ($row['satuan_periode'] ?? 'tahun'))) ?: 'tahun';

            if (! $unitId || ! $jabatanId || $tahun < 2000) {
                $this->errors[] = "Baris {$line}: kode_unit, kode_jabatan atau tahun tidak valid.";

                continue;
            }
            if (empty($row['uraian_tugas']) || ! is_numeric($row['volume'] ?? null) || ! is_numeric($row['norma_waktu'] ?? null)) {
                $this->errors[] = "Baris {$line}: uraian_tugas, volume dan norma_waktu wajib diisi (angka).";

                continue;
            }
            if (! isset(Referensi::PERIODE_PER_TAHUN[$periode])) {
                $this->errors[] = "Baris {$line}: satuan_periode harus tahun/bulan/minggu/hari.";

                continue;
            }

            $groups["{$unitId}-{$jabatanId}-{$tahun}"][] = [
                'unit_kerja_id' => $unitId, 'jabatan_id' => $jabatanId, 'tahun' => $tahun,
                'uraian_tugas' => trim($row['uraian_tugas']), 'hasil_kerja' => $row['hasil_kerja'] ?? null,
                'volume' => max(0, (int) $row['volume']), 'satuan_periode' => $periode, 'norma_waktu' => max(0, (int) $row['norma_waktu']),
            ];
        }

        DB::transaction(function () use ($groups) {
            foreach ($groups as $items) {
                $first = $items[0];
                $anjab = AnjabAbk::firstOrNew([
                    'unit_kerja_id' => $first['unit_kerja_id'], 'jabatan_id' => $first['jabatan_id'], 'tahun' => $first['tahun'],
                ]);

                if ($anjab->exists && $anjab->status === 'final') {
                    $this->errors[] = "ABK tahun {$first['tahun']} untuk posisi (unit {$first['unit_kerja_id']}, jabatan {$first['jabatan_id']}) sudah final; tidak ditimpa.";

                    continue;
                }

                $baru = ! $anjab->exists;
                $anjab->fill(['instansi_id' => $this->instansiId, 'created_by' => $anjab->created_by ?? $this->userId])->save();
                $anjab->uraianTugas()->delete();
                foreach ($items as $n => $item) {
                    $anjab->uraianTugas()->create([
                        'uraian_tugas' => $item['uraian_tugas'], 'hasil_kerja' => $item['hasil_kerja'], 'volume' => $item['volume'],
                        'satuan_periode' => $item['satuan_periode'], 'norma_waktu' => $item['norma_waktu'], 'urutan' => $n + 1,
                    ]);
                    $this->rows++;
                }
                $anjab->recalculate();
                $baru ? $this->created++ : $this->updated++;
            }
        });
    }
}
