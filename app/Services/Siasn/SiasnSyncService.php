<?php

namespace App\Services\Siasn;

use App\Models\Instansi;
use App\Models\Jabatan;
use App\Models\Pegawai;
use App\Models\SiasnSyncLog;
use App\Models\UnitKerja;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sinkronisasi data SIASN ke SIMONKEB:
 *  - unor (unit organisasi) -> unit_kerjas (dipetakan lewat siasn_unor_id)
 *  - data utama PNS per NIP -> pegawais (unit, jabatan, golongan, pendidikan, tgl lahir, status aktif)
 *
 * Nama field respons SIASN dibaca secara toleran (beberapa variasi penulisan)
 * karena format berbeda antar-endpoint/versi.
 */
class SiasnSyncService
{
    private const FIELD = [
        'nip' => ['nipBaru', 'nip_baru', 'nip'],
        'id' => ['id', 'pnsId', 'pns_id'],
        'nama' => ['nama', 'namaLengkap'],
        'tgl_lahir' => ['tglLahir', 'tgl_lahir', 'tanggalLahir'],
        'unor_id' => ['unorId', 'unor_id'],
        'jab_struktural' => ['jabatanStrukturalId', 'jabatan_struktural_id'],
        'jab_fungsional' => ['jabatanFungsionalId', 'jabatan_fungsional_id'],
        'jab_pelaksana' => ['jabatanFungsionalUmumId', 'jabatan_fungsional_umum_id'],
        'jab_nama' => ['jabatanNama', 'jabatan_nama', 'namaJabatan'],
        'golongan' => ['golRuangAkhir', 'gol_ruang_akhir', 'golongan'],
        'pendidikan' => ['pendidikanTerakhirNama', 'pendidikan_terakhir_nama', 'tkPendidikanTerakhir'],
        'tmt_jabatan' => ['tmtJabatan', 'tmt_jabatan'],
        'kedudukan' => ['kedudukanPnsNama', 'kedudukan_pns_nama', 'kedudukanHukumNama'],
        'status_pegawai' => ['statusPegawai', 'status_pegawai', 'jenisPegawaiNama'],
        // ref-unor
        'unor_nama' => ['NamaUnor', 'nama_unor', 'namaUnor', 'nama'],
        'unor_induk' => ['DiatasanId', 'diatasan_id', 'diatasanId', 'atasanId'],
        'unor_instansi' => ['InstansiId', 'instansi_id', 'instansiId'],
        'unor_eselon' => ['EselonId', 'eselon_id', 'eselonId'],
        'unor_jabatan' => ['NamaJabatan', 'nama_jabatan', 'namaJabatan'],
        'unor_id_ref' => ['Id', 'id'],
    ];

    /** Kedudukan hukum yang berarti pegawai tidak lagi aktif. */
    private const TIDAK_AKTIF = ['pensiun', 'berhenti', 'wafat', 'meninggal', 'pemberhentian', 'mpp', 'masa persiapan pensiun'];

    public function __construct(private SiasnClient $client) {}

    public function syncUnor(Instansi $instansi, ?int $userId = null): SiasnSyncLog
    {
        $log = $this->startLog($instansi, 'unor', $userId);

        return $this->run($log, function (SiasnSyncLog $log, array &$pesan) use ($instansi) {
            $rows = $this->client->endpoint('ref_unor');
            if ($instansi->siasn_instansi_id) {
                $rows = array_filter($rows, fn ($r) => (string) $this->val($r, 'unor_instansi') === (string) $instansi->siasn_instansi_id);
            }
            $log->total = count($rows);

            $map = [];
            DB::transaction(function () use ($rows, $instansi, &$map, $log) {
                foreach ($rows as $row) {
                    $unorId = (string) $this->val($row, 'unor_id_ref');
                    if ($unorId === '') {
                        $log->gagal++;

                        continue;
                    }
                    $unit = UnitKerja::firstOrNew(['instansi_id' => $instansi->id, 'siasn_unor_id' => $unorId]);
                    $unit->fill([
                        'nama' => Str::limit(trim((string) $this->val($row, 'unor_nama')) ?: 'Unor '.$unorId, 250, ''),
                        'nama_jabatan_pimpinan' => $this->val($row, 'unor_jabatan'),
                        'eselon' => $this->eselon($this->val($row, 'unor_eselon')) ?? $unit->eselon,
                        'is_active' => true,
                    ])->save();
                    $map[$unorId] = [$unit->id, (string) $this->val($row, 'unor_induk')];
                    $log->berhasil++;
                }

                // hubungkan induk setelah semua unor tersimpan
                foreach ($map as [$unitId, $indukUnorId]) {
                    $parentId = $indukUnorId !== '' && isset($map[$indukUnorId]) ? $map[$indukUnorId][0] : null;
                    if ($parentId !== $unitId) {
                        UnitKerja::whereKey($unitId)->update(['parent_id' => $parentId]);
                    }
                }
            });
        });
    }

    /**
     * @param  string[]|null  $nips  daftar NIP tertentu; null = semua PNS aktif instansi
     */
    public function syncPegawai(Instansi $instansi, ?array $nips = null, ?int $userId = null): SiasnSyncLog
    {
        $log = $this->startLog($instansi, 'pegawai', $userId);

        return $this->run($log, function (SiasnSyncLog $log, array &$pesan) use ($instansi, $nips) {
            $nips = $nips ?: Pegawai::where('instansi_id', $instansi->id)->where('status_kepegawaian', 'pns')
                ->where('is_active', true)->pluck('nip')->all();
            $nips = array_values(array_unique(array_filter(array_map(fn ($n) => preg_replace('/\D/', '', (string) $n), $nips))));
            $log->total = count($nips);

            $units = UnitKerja::where('instansi_id', $instansi->id)->whereNotNull('siasn_unor_id')->pluck('id', 'siasn_unor_id');
            $jabatanBySiasn = Jabatan::whereNotNull('siasn_jabatan_id')->pluck('id', 'siasn_jabatan_id');
            $jabatanByNama = Jabatan::pluck('id', 'nama')->mapWithKeys(fn ($id, $nama) => [Str::lower(trim($nama)) => $id]);

            foreach ($nips as $nip) {
                try {
                    $data = $this->client->endpoint('data_utama', ['nip' => $nip]);
                    if (! $data) {
                        $this->gagal($log, $pesan, "{$nip}: tidak ditemukan di SIASN.");

                        continue;
                    }

                    $unitId = $units[(string) $this->val($data, 'unor_id')] ?? null;
                    $jabatanId = $this->cariJabatan($data, $jabatanBySiasn, $jabatanByNama);
                    $pegawai = Pegawai::where('nip', $nip)->first();

                    if ($pegawai && (int) $pegawai->instansi_id !== $instansi->id) {
                        $this->gagal($log, $pesan, "{$nip}: terdaftar di instansi lain pada SIMONKEB.");

                        continue;
                    }
                    if (! $pegawai && (! $unitId || ! $jabatanId)) {
                        $this->gagal($log, $pesan, "{$nip}: ".(! $unitId ? 'unor '.$this->val($data, 'unor_id').' belum dipetakan' : 'jabatan "'.$this->val($data, 'jab_nama').'" belum dipetakan').'.');

                        continue;
                    }

                    $pegawai ??= new Pegawai(['nip' => $nip, 'instansi_id' => $instansi->id]);
                    $pegawai->keteranganRiwayat = 'Sinkronisasi SIASN';
                    $pegawai->fill(array_filter([
                        'siasn_id' => $this->val($data, 'id'),
                        'nama' => $this->val($data, 'nama'),
                        'unit_kerja_id' => $unitId ?? $pegawai->unit_kerja_id,
                        'jabatan_id' => $jabatanId ?? $pegawai->jabatan_id,
                        'golongan' => $this->val($data, 'golongan'),
                        'pendidikan' => $this->val($data, 'pendidikan'),
                        'tanggal_lahir' => $this->tanggal($this->val($data, 'tgl_lahir')),
                        'tmt_jabatan' => $this->tanggal($this->val($data, 'tmt_jabatan')),
                    ], fn ($v) => $v !== null && $v !== ''));
                    $pegawai->status_kepegawaian = Str::contains(Str::lower((string) $this->val($data, 'status_pegawai')), 'pppk') ? 'pppk' : 'pns';
                    $pegawai->is_active = ! Str::contains(Str::lower((string) $this->val($data, 'kedudukan')), self::TIDAK_AKTIF);
                    $pegawai->sumber = 'siasn';
                    $pegawai->siasn_synced_at = now();
                    $pegawai->save();

                    if (! $unitId || ! $jabatanId) {
                        $pesan[] = "{$nip}: diperbarui sebagian (unor/jabatan SIASN belum dipetakan).";
                    }
                    $log->berhasil++;
                } catch (SiasnException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    $this->gagal($log, $pesan, "{$nip}: ".$e->getMessage());
                }
            }
        });
    }

    private function cariJabatan(array $data, $bySiasn, $byNama): ?int
    {
        foreach (['jab_struktural', 'jab_fungsional', 'jab_pelaksana'] as $key) {
            $id = (string) $this->val($data, $key);
            if ($id !== '' && isset($bySiasn[$id])) {
                return $bySiasn[$id];
            }
        }

        return $byNama[Str::lower(trim((string) $this->val($data, 'jab_nama')))] ?? null;
    }

    private function run(SiasnSyncLog $log, callable $work): SiasnSyncLog
    {
        $pesan = [];
        try {
            $work($log, $pesan);
            $log->status = 'selesai';
        } catch (Throwable $e) {
            $log->status = 'gagal';
            array_unshift($pesan, $e->getMessage());
        }

        $log->pesan = array_slice($pesan, 0, 500);
        $log->finished_at = now();
        $log->save();

        return $log;
    }

    private function startLog(Instansi $instansi, string $jenis, ?int $userId): SiasnSyncLog
    {
        return SiasnSyncLog::create([
            'instansi_id' => $instansi->id, 'user_id' => $userId, 'jenis' => $jenis,
            'status' => 'berjalan', 'started_at' => now(),
        ]);
    }

    private function gagal(SiasnSyncLog $log, array &$pesan, string $message): void
    {
        $log->gagal++;
        $pesan[] = $message;
    }

    private function val(array $row, string $field)
    {
        foreach (self::FIELD[$field] as $key) {
            $value = Arr::get($row, $key);
            if ($value !== null && $value !== '') {
                return is_string($value) ? trim($value) : $value;
            }
        }

        return null;
    }

    /** Tanggal SIASN biasanya "dd-mm-yyyy". */
    private function tanggal($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)
                ? Carbon::createFromFormat('d-m-Y', $value)->toDateString()
                : Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function eselon($eselonId): ?string
    {
        // kode eselon SIASN: 11/12 = I, 21/22 = II, 31/32 = III, 41/42 = IV
        return match (substr((string) $eselonId, 0, 1)) {
            '1' => 'I', '2' => 'II', '3' => 'III', '4' => 'IV', default => null,
        };
    }
}
