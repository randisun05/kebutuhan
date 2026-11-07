<?php

namespace App\Services;

use App\Enums\UsulanStatus;
use App\Models\Instansi;
use App\Models\KebutuhanSementara;
use App\Models\KebutuhanSementaraBatch;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mengisi kebutuhan sementara (jalur manual) dari jumlah pegawai existing atau dari formasi yang sudah ditetapkan.
 * Hanya posisi yang belum punya ABK final dan belum punya kebutuhan sementara yang diisi,
 * sehingga isian yang sudah ada tidak tertimpa. Setiap pengisian massal tercatat sebagai batch yang dapat dibatalkan.
 */
class KebutuhanSementaraService
{
    public const SUMBER = ['existing' => 'Existing', 'formasi' => 'Formasi ditetapkan'];

    /** Status isian baru: menunggu persetujuan bila instansi mewajibkannya dan pengisi bukan administrator. */
    public function statusBaru(Instansi|int $instansi, ?User $user): string
    {
        $instansi = $instansi instanceof Instansi ? $instansi : Instansi::find($instansi);

        return $instansi?->kebutuhan_perlu_persetujuan && ! $user?->isAdmin() ? 'menunggu' : 'disetujui';
    }

    /**
     * Rencana pengisian tanpa menulis apa pun.
     *
     * @param  array<int>|null  $unitIds  batasi ke unit kerja tertentu; null = seluruh instansi
     * @return array{baris: Collection, dilewati_abk: int, dilewati_ada: int}
     */
    public function rencana(int $instansiId, ?array $unitIds, string $sumber = 'existing'): array
    {
        $posisi = $sumber === 'formasi'
            ? $this->posisiFormasi($instansiId, $unitIds)
            : $this->posisiExisting($instansiId, $unitIds);

        $abk = DB::table('anjab_abks')->where('instansi_id', $instansiId)->where('status', 'final')
            ->get(['unit_kerja_id', 'jabatan_id'])->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => true]);
        $ada = KebutuhanSementara::where('instansi_id', $instansiId)
            ->get(['unit_kerja_id', 'jabatan_id'])->mapWithKeys(fn ($r) => [$r->unit_kerja_id.'-'.$r->jabatan_id => true]);

        $hasil = ['baris' => collect(), 'dilewati_abk' => 0, 'dilewati_ada' => 0];
        foreach ($posisi as $p) {
            $kunci = $p->unit_kerja_id.'-'.$p->jabatan_id;
            if (isset($abk[$kunci])) {
                $hasil['dilewati_abk']++;
            } elseif (isset($ada[$kunci])) {
                $hasil['dilewati_ada']++;
            } else {
                $hasil['baris']->push($p);
            }
        }

        return $hasil;
    }

    /** Ringkasan rencana untuk pratinjau: jumlah dan beberapa contoh. */
    public function pratinjau(int $instansiId, ?array $unitIds, string $sumber = 'existing'): array
    {
        $r = $this->rencana($instansiId, $unitIds, $sumber);
        $contoh = $r['baris']->take(8);
        $unit = DB::table('unit_kerjas')->whereIn('id', $contoh->pluck('unit_kerja_id'))->pluck('nama', 'id');
        $jabatan = DB::table('jabatans')->whereIn('id', $contoh->pluck('jabatan_id'))->pluck('nama', 'id');

        return [
            'dibuat' => $r['baris']->count(), 'total_kebutuhan' => (int) $r['baris']->sum('jumlah'),
            'dilewati_abk' => $r['dilewati_abk'], 'dilewati_ada' => $r['dilewati_ada'],
            'contoh' => $contoh->map(fn ($p) => ['unit' => $unit[$p->unit_kerja_id] ?? '-', 'jabatan' => $jabatan[$p->jabatan_id] ?? '-', 'jumlah' => (int) $p->jumlah])->values()->all(),
        ];
    }

    /**
     * @return array{dibuat: int, dilewati_abk: int, dilewati_ada: int, batch: ?string, status: string}
     */
    public function isi(int $instansiId, ?array $unitIds, ?User $user, string $sumber = 'existing'): array
    {
        $r = $this->rencana($instansiId, $unitIds, $sumber);
        $dasar = ($sumber === 'formasi' ? 'Formasi ditetapkan per ' : 'Existing per ').now()->format('d-m-Y');
        $status = $this->statusBaru($instansiId, $user);
        $uuid = null;

        if ($r['baris']->isNotEmpty()) {
            $uuid = (string) Str::uuid();
            DB::transaction(function () use ($r, $instansiId, $user, $dasar, $status, $uuid, $sumber) {
                foreach ($r['baris'] as $p) {
                    KebutuhanSementara::create([
                        'instansi_id' => $instansiId, 'unit_kerja_id' => $p->unit_kerja_id, 'jabatan_id' => $p->jabatan_id,
                        'jumlah' => (int) $p->jumlah, 'dasar' => $dasar, 'batch' => $uuid, 'status' => $status,
                        'disetujui_by' => $status === 'disetujui' ? $user?->id : null,
                        'disetujui_at' => $status === 'disetujui' ? now() : null, 'created_by' => $user?->id,
                    ]);
                }
                KebutuhanSementaraBatch::create([
                    'uuid' => $uuid, 'instansi_id' => $instansiId, 'user_id' => $user?->id, 'sumber' => $sumber, 'jumlah_dibuat' => $r['baris']->count(),
                ]);
            });
        }

        return ['dibuat' => $r['baris']->count(), 'dilewati_abk' => $r['dilewati_abk'], 'dilewati_ada' => $r['dilewati_ada'], 'batch' => $uuid, 'status' => $status];
    }

    /** Kompatibel dengan pemanggil lama. */
    public function isiDariExisting(int $instansiId, ?array $unitIds, ?int $userId): array
    {
        return $this->isi($instansiId, $unitIds, $userId ? User::find($userId) : null, 'existing');
    }

    /**
     * Batalkan satu pengisian massal: hapus baris batch yang belum diubah sejak dibuat.
     *
     * @return array{dihapus: int, dipertahankan: int}
     */
    public function batalkan(KebutuhanSementaraBatch $batch): array
    {
        $baris = KebutuhanSementara::where('batch', $batch->uuid)->get();
        $dihapus = 0;

        DB::transaction(function () use ($baris, $batch, &$dihapus) {
            foreach ($baris as $k) {
                // baris yang sudah diubah pengguna setelah dibuat dipertahankan
                if ($k->updated_at->lte($k->created_at->copy()->addSeconds(1))) {
                    $k->delete();
                    $dihapus++;
                } else {
                    $k->update(['batch' => null]);
                }
            }
            $batch->update(['dibatalkan_at' => now()]);
        });

        return ['dihapus' => $dihapus, 'dipertahankan' => $baris->count() - $dihapus];
    }

    private function posisiExisting(int $instansiId, ?array $unitIds)
    {
        return DB::table('pegawais')
            ->where('is_active', true)
            ->where('instansi_id', $instansiId)
            ->when($unitIds !== null, fn ($q) => $q->whereIn('unit_kerja_id', $unitIds))
            ->groupBy('unit_kerja_id', 'jabatan_id')
            ->selectRaw('unit_kerja_id, jabatan_id, COUNT(*) as jumlah')
            ->get();
    }

    /** Total jumlah ditetapkan per unit + jabatan dari seluruh usulan instansi yang berstatus ditetapkan. */
    private function posisiFormasi(int $instansiId, ?array $unitIds)
    {
        return DB::table('usulan_details as d')
            ->join('usulans as u', 'u.id', '=', 'd.usulan_id')
            ->where('u.instansi_id', $instansiId)
            ->where('u.status', UsulanStatus::Ditetapkan->value)
            ->when($unitIds !== null, fn ($q) => $q->whereIn('d.unit_kerja_id', $unitIds))
            ->groupBy('d.unit_kerja_id', 'd.jabatan_id')
            ->selectRaw('d.unit_kerja_id, d.jabatan_id, COALESCE(SUM(d.jumlah_ditetapkan), 0) as jumlah')
            ->havingRaw('COALESCE(SUM(d.jumlah_ditetapkan), 0) > 0')
            ->get();
    }
}
