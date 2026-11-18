<?php

namespace App\Services\JfPembina;

use App\Services\Siasn\SiasnException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Klien API JF instansi pembina BKN (produk "pusbin"), disalin dari ujikom-pusbin (SiasnService).
 *
 * Temuan eksplorasi live di ujikom (2026-11-17):
 *  - list-asn-pembinajf: `instansi` dan `limit` WAJIB; limit maks 100 (500 -> "Send timeout");
 *    `offset` bekerja; tiap baris HANYA {id, nip, nama}; belum ada filter jabatan.
 *  - Gateway tidak stabil (timeout/reset acak) dan lambat (~8 dtk/NIP untuk data-utama), maka
 *    permintaan diulang dan detail diambil paralel.
 */
class JfPembinaClient
{
    private const CACHE_APIM = 'jfpembina.token.apim';

    private const CACHE_SSO = 'jfpembina.token.sso';

    public function isConfigured(): bool
    {
        return config('jfpembina.enabled')
            && config('jfpembina.apim.username') && config('jfpembina.apim.password')
            && (config('jfpembina.sso.access_token') || (config('jfpembina.sso.username') && config('jfpembina.sso.password')));
    }

    /**
     * Satu halaman daftar ASN suatu instansi BKN. null bila tetap gagal setelah $percobaan kali.
     *
     * @return array{total: int, rows: array<int, array{id: ?string, nip: ?string, nama: ?string}>}|null
     */
    public function daftarAsn(string $instansiBkn, int $limit = 100, int $offset = 0, int $percobaan = 3): ?array
    {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        for ($coba = 1; $coba <= $percobaan; $coba++) {
            try {
                $response = $this->request()->get($this->url(config('jfpembina.endpoints.list')), [
                    'instansi' => $instansiBkn, 'limit' => $limit, 'offset' => max(0, $offset),
                ]);

                if ($response->status() === 401) {
                    $this->forgetTokens();
                } elseif ($response->successful()) {
                    $json = $response->json() ?? [];

                    return [
                        'total' => (int) ($json['count'] ?? 0),
                        'rows' => is_array($json['data'] ?? null) ? $json['data'] : [],
                    ];
                }
                Log::warning("JfPembina: daftar ASN instansi {$instansiBkn} HTTP {$response->status()} (percobaan {$coba}/{$percobaan})");
            } catch (Throwable $e) {
                Log::warning("JfPembina: daftar ASN instansi {$instansiBkn} gagal (percobaan {$coba}/{$percobaan}): ".$e->getMessage());
            }

            if ($coba < $percobaan) {
                usleep(500000 * $coba);
            }
        }

        return null;
    }

    /**
     * Detail jabatan satu ASN (data-utama/{nip}). null bila tetap gagal.
     *
     * @return array<string, ?string>|null
     */
    public function detail(string $nip, int $percobaan = 3): ?array
    {
        for ($coba = 1; $coba <= $percobaan; $coba++) {
            try {
                $response = $this->request()->get($this->detailUrl($nip));
                if ($response->status() === 401) {
                    $this->forgetTokens();
                } elseif ($response->successful() && ($mapped = $this->petakanDetail($response->json('data')))) {
                    return $mapped;
                }
            } catch (Throwable $e) {
                Log::warning("JfPembina: detail NIP {$nip} gagal (percobaan {$coba}/{$percobaan}): ".$e->getMessage());
            }

            if ($coba < $percobaan) {
                usleep(500000 * $coba);
            }
        }

        return null;
    }

    /**
     * Detail banyak NIP sekaligus (paralel). Yang gagal diulang sampai $ronde kali; 401 membuang
     * token sehingga ronde berikutnya memakai token baru.
     *
     * @param  array<int, string>  $nips
     * @return array<string, array<string, ?string>|null> nip => detail, null bila tetap gagal
     */
    public function detailBanyak(array $nips, ?int $konkurensi = null, int $ronde = 3): array
    {
        $hasil = array_fill_keys($nips, null);
        $sisa = array_values(array_unique($nips));
        $konkurensi = max(1, $konkurensi ?? (int) config('jfpembina.concurrency'));

        for ($putaran = 1; $putaran <= $ronde && $sisa !== []; $putaran++) {
            $gagal = [];

            try {
                $headers = $this->headers();
            } catch (Throwable $e) {
                Log::warning("JfPembina: token untuk detail gagal (ronde {$putaran}/{$ronde}): ".$e->getMessage());
                $sisa = array_values($sisa);
                if ($putaran < $ronde) {
                    sleep(2 * $putaran);
                }

                continue;
            }

            $responses = Http::pool(function (Pool $pool) use ($sisa, $headers) {
                foreach ($sisa as $nip) {
                    $pool->as((string) $nip)->withHeaders($headers)->acceptJson()
                        ->timeout(config('jfpembina.timeout'))
                        ->withOptions(['verify' => config('jfpembina.verify_ssl')])
                        ->get($this->detailUrl((string) $nip));
                }
            }, $konkurensi);

            foreach ($sisa as $nip) {
                $response = $responses[(string) $nip] ?? null;

                if (! $response instanceof Response) {
                    $gagal[] = $nip; // ConnectionException / tidak ada respons

                    continue;
                }
                if ($response->status() === 401) {
                    $this->forgetTokens();
                } elseif ($response->successful() && ($mapped = $this->petakanDetail($response->json('data')))) {
                    $hasil[$nip] = $mapped;

                    continue;
                }
                $gagal[] = $nip;
            }

            $sisa = $gagal;
            if ($sisa !== [] && $putaran < $ronde) {
                sleep(2 * $putaran);
            }
        }

        return $hasil;
    }

    public function testConnection(): array
    {
        $this->forgetTokens();

        return ['apim' => (bool) $this->apimToken(), 'sso' => (bool) $this->ssoToken()];
    }

    public function forgetTokens(): void
    {
        Cache::forget(self::CACHE_APIM);
        Cache::forget(self::CACHE_SSO);
    }

    /** @return array<string, ?string>|null null bila `data` kosong/bukan array */
    public function petakanDetail(mixed $d): ?array
    {
        if (! is_array($d) || $d === []) {
            return null;
        }

        // BKN memberi tmtJabatan dd-mm-yyyy
        $tmt = null;
        if (! empty($d['tmtJabatan']) && preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $d['tmtJabatan'], $m)) {
            $tmt = "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return [
            'jabatan_fungsional_id' => ($d['jabatanFungsionalId'] ?? '') ?: null,
            'jabatan_fungsional_nama' => ($d['jabatanFungsionalNama'] ?? '') ?: (($d['jabatanNama'] ?? '') ?: null),
            'unor_nama' => ($d['unorNama'] ?? '') ?: null,
            'gol_ruang' => ($d['golRuangAkhir'] ?? '') ?: null,
            'tmt_jabatan' => $tmt,
            'kedudukan' => ($d['kedudukanPnsNama'] ?? '') ?: null,
            'instansi_kerja_id' => ($d['instansiKerjaId'] ?? '') ?: null,
        ];
    }

    private function url(string $path): string
    {
        return rtrim(config('jfpembina.base_url'), '/').'/'.ltrim($path, '/');
    }

    private function detailUrl(string $nip): string
    {
        return $this->url(str_replace('{nip}', rawurlencode($nip), config('jfpembina.endpoints.detail')));
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        if (! $this->isConfigured()) {
            throw new SiasnException('Integrasi JF Pembina belum dikonfigurasi (lihat JFPEMBINA_* pada .env).');
        }

        return ['Authorization' => 'Bearer '.$this->apimToken(), 'Auth' => 'Bearer '.$this->ssoToken()];
    }

    private function request(): PendingRequest
    {
        return Http::timeout(config('jfpembina.timeout'))
            ->withOptions(['verify' => config('jfpembina.verify_ssl')])
            ->acceptJson()
            ->withHeaders($this->headers());
    }

    private function apimToken(): string
    {
        return Cache::remember(self::CACHE_APIM, max(60, config('jfpembina.apim.ttl') - 60), function () {
            $response = Http::asForm()->timeout(config('jfpembina.timeout'))
                ->withOptions(['verify' => config('jfpembina.verify_ssl')])
                ->withBasicAuth(config('jfpembina.apim.username'), config('jfpembina.apim.password'))
                ->post(config('jfpembina.apim.url'), ['grant_type' => 'client_credentials']);

            return $response->successful() && $response->json('access_token')
                ? $response->json('access_token')
                : throw new SiasnException('Gagal memperoleh token APIM BKN (HTTP '.$response->status().').');
        });
    }

    private function ssoToken(): string
    {
        if ($token = trim((string) config('jfpembina.sso.access_token'))) {
            $token = preg_replace('/^bearer\s+/i', '', $token);
            $exp = $this->jwtExp($token);
            if ($exp !== null && $exp <= time()) {
                Log::warning('JfPembina: JFPEMBINA_AUTH_TOKEN sudah kedaluwarsa sejak '.date('Y-m-d H:i:s', $exp).' - tempel token baru dari sesi SIASN.');
            }

            return $token;
        }

        return Cache::remember(self::CACHE_SSO, max(60, config('jfpembina.sso.ttl') - 60), function () {
            $response = Http::asForm()->timeout(config('jfpembina.timeout'))
                ->withOptions(['verify' => config('jfpembina.verify_ssl')])
                ->post(config('jfpembina.sso.url'), [
                    'grant_type' => 'password',
                    'client_id' => config('jfpembina.sso.client_id'),
                    'username' => config('jfpembina.sso.username'),
                    'password' => config('jfpembina.sso.password'),
                ]);

            return $response->successful() && $response->json('access_token')
                ? $response->json('access_token')
                : throw new SiasnException('Gagal memperoleh token SSO SIASN (HTTP '.$response->status().'); tempel token manual di JFPEMBINA_AUTH_TOKEN.');
        });
    }

    /** Klaim `exp` JWT (tanpa verifikasi tanda tangan, hanya diagnosis). */
    private function jwtExp(string $jwt): ?int
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return isset($payload['exp']) ? (int) $payload['exp'] : null;
    }
}
