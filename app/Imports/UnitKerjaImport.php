<?php

namespace App\Imports;

use App\Models\UnitKerja;
use App\Support\Referensi;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Impor struktur organisasi. Unit dicocokkan berdasarkan kode di dalam instansi;
 * induk dihubungkan setelah semua baris terbaca sehingga urutan baris bebas.
 */
class UnitKerjaImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public array $errors = [];

    public function __construct(private int $instansiId) {}

    public function collection(Collection $rows): void
    {
        $induk = [];

        foreach ($rows as $i => $row) {
            $line = $i + 2;
            $kode = trim((string) ($row['kode'] ?? ''));
            $nama = trim((string) ($row['nama'] ?? ''));

            if ($kode === '' || $nama === '') {
                $this->errors[] = "Baris {$line}: kode dan nama wajib diisi.";

                continue;
            }
            $eselon = trim((string) ($row['eselon'] ?? '')) ?: null;
            if ($eselon && ! in_array($eselon, Referensi::ESELON, true)) {
                $this->errors[] = "Baris {$line}: eselon \"{$eselon}\" tidak dikenal (I, II, III, IV, non).";
                $eselon = null;
            }

            $unit = UnitKerja::firstOrNew(['instansi_id' => $this->instansiId, 'kode' => $kode]);
            $baru = ! $unit->exists;
            $unit->fill([
                'nama' => $nama,
                'eselon' => $eselon,
                'nama_jabatan_pimpinan' => trim((string) ($row['nama_jabatan_pimpinan'] ?? '')) ?: $unit->nama_jabatan_pimpinan,
                'siasn_unor_id' => trim((string) ($row['siasn_unor_id'] ?? '')) ?: $unit->siasn_unor_id,
                'urutan' => $i,
            ])->save();

            $baru ? $this->created++ : $this->updated++;
            $induk[$unit->id] = trim((string) ($row['kode_induk'] ?? ''));
        }

        $byKode = UnitKerja::where('instansi_id', $this->instansiId)->whereNotNull('kode')->pluck('id', 'kode');
        foreach ($induk as $unitId => $kodeInduk) {
            if ($kodeInduk === '') {
                continue;
            }
            $parentId = $byKode[$kodeInduk] ?? null;
            if (! $parentId || $parentId === $unitId) {
                $this->errors[] = "Kode induk \"{$kodeInduk}\" tidak ditemukan.";

                continue;
            }
            UnitKerja::whereKey($unitId)->update(['parent_id' => $parentId]);
        }
    }
}
