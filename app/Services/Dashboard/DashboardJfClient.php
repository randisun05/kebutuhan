<?php

namespace App\Services\Dashboard;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Klien API Dashboard untuk data JF (bezetting kondisi kini per instansi × jabatan × jenjang). Alamat, autentikasi, nama
 * parameter, dan nama field respons dibaca dari config/dashboardjf.php karena spesifikasi API masih dapat berubah.
 * Mendukung paginasi (parameter halaman + field halaman terakhir). Tidak pernah menulis ke database.
 */
class DashboardJfClient
{
    public function aktif(): bool
    {
        return (bool) config('dashboardjf.enabled') && filled(config('dashboardjf.base_url'));
    }

    /**
     * Ambil seluruh baris untuk satu pembina.
     *
     * @return array{per: ?string, baris: array<int, array{instansi_id: ?string, instansi_kode: ?string, instansi_nama: ?string, jabatan: ?string, jenjang: ?string, jumlah: ?int}>}
     */
    public function ambil(string $pembinaKode, ?string $per = null): array
    {
        if (! $this->aktif()) {
            throw new DashboardJfException('Integrasi API Dashboard belum aktif. Isi DASHBOARD_JF_ENABLED=true dan DASHBOARD_JF_BASE_URL (lihat docs/API-DASHBOARD.md).');
        }

        $cfg = config('dashboardjf');
        $f = $cfg['respons'];
        $baris = [];
        $perDiterima = null;
        $halaman = 1;

        do {
            $query = array_filter([
                $cfg['param']['pembina'] => $pembinaKode, $cfg['param']['per'] => $per,
                $cfg['param']['halaman'] => $halaman, $cfg['param']['ukuran'] => $cfg['ukuran_halaman'],
            ], fn ($v) => $v !== null && $v !== '');

            try {
                $res = $this->request()->get(rtrim((string) $cfg['base_url'], '/').'/'.ltrim((string) $cfg['endpoint'], '/'), $query);
            } catch (Throwable $e) {
                throw new DashboardJfException('Tidak dapat menghubungi API Dashboard: '.$e->getMessage(), 0, $e);
            }
            if ($res->status() === 401 || $res->status() === 403) {
                throw new DashboardJfException("API Dashboard menolak akses (HTTP {$res->status()}). Periksa DASHBOARD_JF_TOKEN dan DASHBOARD_JF_AUTH_*.");
            }
            if (! $res->successful()) {
                throw new DashboardJfException("API Dashboard gagal (HTTP {$res->status()}).");
            }

            $json = $res->json();
            $data = Arr::get((array) $json, $f['data']);
            if (! is_array($data)) {
                throw new DashboardJfException("Respons API Dashboard tidak memuat field \"{$f['data']}\" berisi daftar. Sesuaikan DASHBOARD_JF_FIELD_DATA.");
            }
            $perDiterima ??= Arr::get((array) $json, $f['per']);

            foreach ($data as $r) {
                $jumlah = Arr::get((array) $r, $f['jumlah']);
                $baris[] = [
                    'instansi_id' => $this->teks(Arr::get((array) $r, $f['instansi_id'])), 'instansi_kode' => $this->teks(Arr::get((array) $r, $f['instansi_kode'])),
                    'instansi_nama' => $this->teks(Arr::get((array) $r, $f['instansi_nama'])), 'jabatan' => $this->teks(Arr::get((array) $r, $f['jabatan'])),
                    'jenjang' => $this->teks(Arr::get((array) $r, $f['jenjang'])), 'jumlah' => is_numeric($jumlah) ? (int) round((float) $jumlah) : null,
                ];
            }

            $terakhir = (int) (Arr::get((array) $json, $f['halaman_terakhir']) ?: 1);
            $halaman++;
        } while ($halaman <= $terakhir && $data !== [] && $halaman <= 10000);

        return ['per' => is_string($perDiterima) ? $perDiterima : $per, 'baris' => $baris];
    }

    private function request(): PendingRequest
    {
        $req = Http::acceptJson()->timeout((int) config('dashboardjf.timeout'));
        if (! config('dashboardjf.verify_ssl')) {
            $req = $req->withoutVerifying();
        }
        if (filled($token = config('dashboardjf.token'))) {
            $skema = trim((string) config('dashboardjf.auth_skema'));
            $req = $req->withHeaders([config('dashboardjf.auth_header') => ($skema !== '' ? $skema.' ' : '').$token]);
        }

        return $req;
    }

    private function teks(mixed $v): ?string
    {
        return $v === null || $v === '' ? null : trim((string) $v);
    }
}
