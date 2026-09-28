<?php

namespace App\Services\Siasn;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Klien HTTP web service SIASN BKN (token APIM + token SSO).
 */
class SiasnClient
{
    public function isConfigured(): bool
    {
        return config('siasn.enabled')
            && config('siasn.apim.username') && config('siasn.apim.password')
            && (config('siasn.sso.access_token') || (config('siasn.sso.client_id') && config('siasn.sso.username') && config('siasn.sso.password')));
    }

    public function get(string $path, array $query = []): array
    {
        $response = $this->request()->get(rtrim(config('siasn.base_url'), '/').'/'.ltrim($path, '/'), $query);

        // token kedaluwarsa: minta token baru sekali lalu ulangi
        if ($response->status() === 401) {
            $this->forgetTokens();
            $response = $this->request()->get(rtrim(config('siasn.base_url'), '/').'/'.ltrim($path, '/'), $query);
        }

        return $this->decode($response, $path);
    }

    /** GET dengan placeholder, contoh endpoint('data_utama', ['nip' => '...']). */
    public function endpoint(string $name, array $params = [], array $query = []): array
    {
        $path = config("siasn.endpoints.{$name}");
        foreach ($params as $key => $value) {
            $path = str_replace('{'.$key.'}', rawurlencode((string) $value), $path);
        }

        return $this->get($path, $query);
    }

    public function testConnection(): array
    {
        $this->forgetTokens();

        return [
            'apim' => (bool) $this->apimToken(),
            'sso' => (bool) $this->ssoToken(),
        ];
    }

    public function forgetTokens(): void
    {
        Cache::forget('siasn.token.apim');
        Cache::forget('siasn.token.sso');
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new SiasnException('Integrasi SIASN belum dikonfigurasi (lihat SIASN_* pada .env).');
        }

        return Http::timeout(config('siasn.timeout'))
            ->withOptions(['verify' => config('siasn.verify_ssl')])
            ->acceptJson()
            ->withToken($this->apimToken())
            ->withHeaders(['Auth' => 'Bearer '.$this->ssoToken()]);
    }

    private function apimToken(): string
    {
        return Cache::remember('siasn.token.apim', max(60, config('siasn.apim.ttl') - 60), function () {
            $response = Http::asForm()->timeout(config('siasn.timeout'))
                ->withOptions(['verify' => config('siasn.verify_ssl')])
                ->withBasicAuth(config('siasn.apim.username'), config('siasn.apim.password'))
                ->post(config('siasn.apim.url'), ['grant_type' => 'client_credentials']);

            return $response->successful() && $response->json('access_token')
                ? $response->json('access_token')
                : throw new SiasnException('Gagal memperoleh token APIM SIASN (HTTP '.$response->status().').');
        });
    }

    private function ssoToken(): string
    {
        if ($token = config('siasn.sso.access_token')) {
            return $token;
        }

        return Cache::remember('siasn.token.sso', max(60, config('siasn.sso.ttl') - 60), function () {
            $response = Http::asForm()->timeout(config('siasn.timeout'))
                ->withOptions(['verify' => config('siasn.verify_ssl')])
                ->post(config('siasn.sso.url'), [
                    'grant_type' => 'password',
                    'client_id' => config('siasn.sso.client_id'),
                    'username' => config('siasn.sso.username'),
                    'password' => config('siasn.sso.password'),
                ]);

            return $response->successful() && $response->json('access_token')
                ? $response->json('access_token')
                : throw new SiasnException('Gagal memperoleh token SSO SIASN (HTTP '.$response->status().').');
        });
    }

    private function decode(Response $response, string $path): array
    {
        if ($response->status() === 404) {
            return [];
        }
        if ($response->failed()) {
            throw new SiasnException("SIASN {$path}: HTTP {$response->status()} ".mb_substr($response->body(), 0, 200));
        }

        $json = $response->json() ?? [];

        // respons SIASN umumnya berbentuk {"code":1,"data":...}
        return is_array($json) && array_key_exists('data', $json) ? (array) ($json['data'] ?? []) : (array) $json;
    }
}
