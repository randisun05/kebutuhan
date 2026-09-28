<?php

namespace App\Imports;

use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\UnitKerja;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Impor data pegawai existing (bezetting). Kolom:
 * nip, nama, kode_unit, kode_jabatan, status_kepegawaian, golongan, pendidikan, tanggal_lahir, tmt_jabatan
 * Pegawai dengan NIP yang sudah ada akan diperbarui (mutasi/rotasi ikut tercatat).
 */
class PegawaiImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public array $errors = [];

    public function __construct(private int $instansiId) {}

    public function collection(Collection $rows): void
    {
        $units = UnitKerja::where('instansi_id', $this->instansiId)->whereNotNull('kode')->pluck('id', 'kode');
        $jabatans = Jabatan::pluck('id', 'kode');

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $nip = preg_replace('/\D/', '', (string) ($row['nip'] ?? ''));

            if (strlen($nip) !== 18) {
                $this->errors[] = "Baris {$line}: NIP harus 18 digit.";

                continue;
            }
            $unitId = $units[(string) ($row['kode_unit'] ?? '')] ?? null;
            $jabatanId = $jabatans[(string) ($row['kode_jabatan'] ?? '')] ?? null;
            if (! $unitId || ! $jabatanId || empty($row['nama'])) {
                $this->errors[] = "Baris {$line}: nama, kode_unit atau kode_jabatan tidak valid.";

                continue;
            }

            $existing = Pegawai::where('nip', $nip)->first();
            if ($existing && (int) $existing->instansi_id !== $this->instansiId) {
                $this->errors[] = "Baris {$line}: NIP {$nip} terdaftar di instansi lain.";

                continue;
            }

            $pegawai = $existing ?? new Pegawai(['nip' => $nip]);
            $pegawai->fill([
                'instansi_id' => $this->instansiId,
                'unit_kerja_id' => $unitId,
                'jabatan_id' => $jabatanId,
                'nama' => trim($row['nama']),
                'status_kepegawaian' => strtolower((string) ($row['status_kepegawaian'] ?? 'pns')) === 'pppk' ? 'pppk' : 'pns',
                'golongan' => $row['golongan'] ?? null,
                'pendidikan' => $row['pendidikan'] ?? null,
                'tanggal_lahir' => $this->date($row['tanggal_lahir'] ?? null),
                'tmt_jabatan' => $this->date($row['tmt_jabatan'] ?? null),
                'is_active' => true,
                'sumber' => 'import',
            ]);
            $pegawai->keteranganRiwayat = 'Impor Excel';
            $pegawai->save();

            $existing ? $this->updated++ : $this->created++;
        }
    }

    private function date($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return is_numeric($value)
                ? Carbon::instance(ExcelDate::excelToDateTimeObject($value))->toDateString()
                : Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
