<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\UsulanStatus as S;
use App\Models\Penetapan;
use App\Models\User;
use App\Models\Usulan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mesin status usulan kebutuhan, dari pengajuan instansi sampai penetapan:
 *
 *   draft/dikembalikan --ajukan--> diajukan --mulai_verifikasi--> verifikasi_bkn
 *   --rekomendasi (pertimbangan teknis BKN)--> pertimbangan_teknis
 *   --mulai_validasi--> validasi_kemenpan --tetapkan--> ditetapkan
 *
 * Pada tahap verifikasi/validasi usulan dapat dikembalikan untuk perbaikan atau ditolak.
 */
class UsulanWorkflow
{
    /** aksi => [status asal yang diizinkan, status tujuan, peran yang diizinkan] */
    public const TRANSITIONS = [
        'ajukan' => [[S::Draft, S::Dikembalikan], S::Diajukan, [Role::OperatorInstansi, Role::Admin]],
        'mulai_verifikasi' => [[S::Diajukan], S::VerifikasiBkn, [Role::VerifikatorBkn, Role::Admin]],
        'rekomendasi' => [[S::VerifikasiBkn], S::PertimbanganTeknis, [Role::VerifikatorBkn, Role::Admin]],
        'mulai_validasi' => [[S::PertimbanganTeknis], S::ValidasiKemenpan, [Role::ValidatorKemenpan, Role::Admin]],
        'tetapkan' => [[S::ValidasiKemenpan], S::Ditetapkan, [Role::ValidatorKemenpan, Role::Admin]],
        'kembalikan' => [[S::Diajukan, S::VerifikasiBkn, S::PertimbanganTeknis, S::ValidasiKemenpan], S::Dikembalikan, [Role::VerifikatorBkn, Role::ValidatorKemenpan, Role::Admin]],
        'tolak' => [[S::Diajukan, S::VerifikasiBkn, S::PertimbanganTeknis, S::ValidasiKemenpan], S::Ditolak, [Role::VerifikatorBkn, Role::ValidatorKemenpan, Role::Admin]],
    ];

    public const LABELS = [
        'ajukan' => 'Ajukan Usulan',
        'mulai_verifikasi' => 'Mulai Verifikasi',
        'rekomendasi' => 'Beri Pertimbangan Teknis',
        'mulai_validasi' => 'Mulai Validasi',
        'tetapkan' => 'Tetapkan Kebutuhan',
        'kembalikan' => 'Kembalikan untuk Perbaikan',
        'tolak' => 'Tolak Usulan',
    ];

    /** Tahap yang menjadi kewenangan masing-masing instansi pembina. */
    private const STAGE_OWNER = [
        Role::VerifikatorBkn->value => [S::Diajukan, S::VerifikasiBkn],
        Role::ValidatorKemenpan->value => [S::PertimbanganTeknis, S::ValidasiKemenpan],
    ];

    public function can(User $user, Usulan $usulan, string $aksi): bool
    {
        if (! isset(self::TRANSITIONS[$aksi]) || ! $user->is_active) {
            return false;
        }

        [$from, , $roles] = self::TRANSITIONS[$aksi];

        if (! in_array($usulan->status, $from, true) || ! $user->hasRole(...$roles)) {
            return false;
        }

        if (! $user->canAccessInstansi($usulan->instansi_id)) {
            return false;
        }

        // BKN hanya dapat mengembalikan/menolak pada tahap BKN, KemenPANRB pada tahapnya
        if (in_array($aksi, ['kembalikan', 'tolak'], true) && isset(self::STAGE_OWNER[$user->role->value])) {
            return in_array($usulan->status, self::STAGE_OWNER[$user->role->value], true);
        }

        return true;
    }

    /** Daftar aksi yang tersedia bagi user untuk ditampilkan sebagai tombol. */
    public function availableActions(User $user, Usulan $usulan): array
    {
        return collect(array_keys(self::TRANSITIONS))
            ->filter(fn ($aksi) => $this->can($user, $usulan, $aksi))
            ->map(fn ($aksi) => ['aksi' => $aksi, 'label' => self::LABELS[$aksi]])
            ->values()->all();
    }

    /**
     * Jalankan transisi. $payload dapat berisi:
     * - catatan (wajib untuk kembalikan/tolak)
     * - details: [detail_id => jumlah] untuk rekomendasi / tetapkan
     * - nomor_sk, tanggal_sk, file_sk, keterangan untuk tetapkan
     */
    public function transition(User $user, Usulan $usulan, string $aksi, array $payload = []): Usulan
    {
        return DB::transaction(function () use ($user, $usulan, $aksi, $payload) {
            // kunci baris agar dua verifikator tidak memproses usulan yang sama bersamaan
            $usulan = Usulan::whereKey($usulan->id)->lockForUpdate()->firstOrFail();

            if (! $this->can($user, $usulan, $aksi)) {
                throw ValidationException::withMessages([
                    'aksi' => 'Aksi "'.(self::LABELS[$aksi] ?? $aksi).'" tidak dapat dilakukan pada status '.$usulan->status->label().'.',
                ]);
            }

            $catatan = trim((string) ($payload['catatan'] ?? '')) ?: null;
            [, $to] = self::TRANSITIONS[$aksi];

            match ($aksi) {
                'ajukan' => $this->guardAjukan($usulan),
                'kembalikan', 'tolak' => $catatan ?: throw ValidationException::withMessages(['catatan' => 'Catatan wajib diisi.']),
                'rekomendasi' => $this->applyJumlah($usulan, $payload['details'] ?? [], 'jumlah_rekomendasi', 'jumlah_usul'),
                'tetapkan' => $this->tetapkan($user, $usulan, $payload),
                default => null,
            };

            $from = $usulan->status;
            $usulan->status = $to;
            $usulan->catatan_terakhir = $catatan ?? $usulan->catatan_terakhir;
            if ($aksi === 'ajukan') {
                $usulan->diajukan_at = now();
            }
            if ($aksi === 'tetapkan') {
                $usulan->ditetapkan_at = now();
            }
            $usulan->save();

            $usulan->logs()->create([
                'user_id' => $user->id,
                'aksi' => $aksi,
                'dari_status' => $from->value,
                'ke_status' => $to->value,
                'catatan' => $catatan,
            ]);

            return $usulan;
        });
    }

    private function guardAjukan(Usulan $usulan): void
    {
        $details = $usulan->details()->get();

        if ($details->isEmpty() || $details->sum('jumlah_usul') < 1) {
            throw ValidationException::withMessages(['details' => 'Usulan belum memiliki rincian jabatan yang diusulkan.']);
        }
    }

    /**
     * Isi jumlah rekomendasi/penetapan per rincian. Nilai tidak boleh melebihi
     * angka pada tahap sebelumnya. Rincian yang tidak dikirim memakai angka tahap sebelumnya.
     */
    private function applyJumlah(Usulan $usulan, array $input, string $field, string $capField): int
    {
        $total = 0;
        $errors = [];

        foreach ($usulan->details()->get() as $detail) {
            $cap = (int) ($detail->{$capField} ?? 0);
            $value = array_key_exists($detail->id, $input) ? (int) $input[$detail->id] : $cap;

            if ($value < 0 || $value > $cap) {
                $errors["details.{$detail->id}"] = "Jumlah harus antara 0 dan {$cap}.";

                continue;
            }

            $detail->update([$field => $value]);
            $total += $value;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $total;
    }

    private function tetapkan(User $user, Usulan $usulan, array $payload): void
    {
        if (empty($payload['nomor_sk']) || empty($payload['tanggal_sk'])) {
            throw ValidationException::withMessages(['nomor_sk' => 'Nomor dan tanggal SK penetapan wajib diisi.']);
        }

        $total = $this->applyJumlah($usulan, $payload['details'] ?? [], 'jumlah_ditetapkan', 'jumlah_rekomendasi');

        Penetapan::create([
            'usulan_id' => $usulan->id,
            'instansi_id' => $usulan->instansi_id,
            'nomor_sk' => $payload['nomor_sk'],
            'tanggal_sk' => $payload['tanggal_sk'],
            'tahun' => $usulan->tahun,
            'total_ditetapkan' => $total,
            'file_sk' => $payload['file_sk'] ?? null,
            'keterangan' => $payload['keterangan'] ?? null,
            'ditetapkan_oleh' => $user->id,
        ]);
    }
}
