<?php

namespace App\Imports;

use App\Models\Jabatan;
use App\Models\KebutuhanSementara;
use App\Models\UnitKerja;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Impor kebutuhan sementara. Satu baris = satu posisi (kode_unit + kode_jabatan).
 * Posisi yang sudah ada diperbarui; jumlah 0 menghapusnya. Posisi yang sudah punya ABK final
 * tetap disimpan, tetapi yang dipakai tetap hasil ABK (dihitung sebagai "sudah ber-ABK").
 */
class KebutuhanSementaraImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $deleted = 0;

    public int $adaAbk = 0;

    public array $errors = [];

    public function __construct(private int $instansiId, private ?int $userId, public string $status = 'disetujui') {}

    public function collection(Collection $rows): void
    {
        $units = UnitKerja::where('instansi_id', $this->instansiId)->whereNotNull('kode')->pluck('id', 'kode');
        $jabatans = Jabatan::pluck('id', 'kode');
        $abk = DB::table('anjab_abks')->where('instansi_id', $this->instansiId)->where('status', 'final')
            ->get(['unit_kerja_id', 'jabatan_id'])->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => true]);

        DB::transaction(function () use ($rows, $units, $jabatans, $abk) {
            foreach ($rows as $i => $row) {
                $line = $i + 2;
                $unitId = $units[trim((string) ($row['kode_unit'] ?? ''))] ?? null;
                $jabatanId = $jabatans[trim((string) ($row['kode_jabatan'] ?? ''))] ?? null;
                $jumlah = $row['jumlah'] ?? null;

                if (! $unitId || ! $jabatanId) {
                    $this->errors[] = "Baris {$line}: kode_unit atau kode_jabatan tidak dikenal di instansi ini.";

                    continue;
                }
                if ($jumlah === null || trim((string) $jumlah) === '') {
                    $this->errors[] = "Baris {$line}: jumlah kosong. Isi angka kebutuhan (0 untuk menghapus posisi ini).";

                    continue;
                }
                if (! is_numeric($jumlah) || (int) $jumlah < 0 || (int) $jumlah > 10000 || (float) $jumlah != (int) $jumlah) {
                    $this->errors[] = "Baris {$line}: jumlah harus bilangan bulat 0–10000.";

                    continue;
                }

                $kunci = ['unit_kerja_id' => $unitId, 'jabatan_id' => $jabatanId];
                $model = KebutuhanSementara::where($kunci)->first();

                if ((int) $jumlah === 0) {
                    if ($model) {
                        $model->delete();
                        $this->deleted++;
                    }

                    continue;
                }

                $dasar = mb_substr(trim((string) ($row['dasar'] ?? '')), 0, 255) ?: null;
                if ($model) {
                    $model->update(['jumlah' => (int) $jumlah, 'dasar' => $dasar ?? $model->dasar] + $this->persetujuan());
                    $this->updated++;
                } else {
                    KebutuhanSementara::create($kunci + [
                        'instansi_id' => $this->instansiId, 'jumlah' => (int) $jumlah, 'dasar' => $dasar, 'created_by' => $this->userId,
                    ] + $this->persetujuan());
                    $this->created++;
                }
                if (isset($abk[$unitId.'-'.$jabatanId])) {
                    $this->adaAbk++;
                }
            }
        });
    }

    /** Kolom status/persetujuan sesuai status isian (menunggu atau disetujui). */
    private function persetujuan(): array
    {
        return [
            'status' => $this->status,
            'disetujui_by' => $this->status === 'disetujui' ? $this->userId : null,
            'disetujui_at' => $this->status === 'disetujui' ? now() : null,
        ];
    }
}
