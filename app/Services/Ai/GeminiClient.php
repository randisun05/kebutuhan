<?php

namespace App\Services\Ai;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien Google Gemini (generateContent) dengan rotasi beberapa API key.
 *
 * Tier gratis dibatasi per menit dan per hari, jadi beberapa key dipakai bergiliran (round-robin).
 * Key yang kena rate limit (429) atau ditolak diistirahatkan sementara dan dilewati; permintaan
 * langsung dicoba ulang dengan key berikutnya. Setelah masa istirahat habis key ikut bergiliran lagi.
 * Kuota harian yang habis diistirahatkan sampai reset (tengah malam waktu Pasifik).
 */
class GeminiClient
{
    private const CACHE = 'gemini.';

    public function aktif(): bool
    {
        return config('gemini.enabled') && count($this->keys()) > 0;
    }

    /**
     * Kirim prompt dan kembalikan teks jawaban.
     *
     * Opsi: system (instruksi sistem), temperature, json (true = minta JSON), schema (responseSchema).
     * Klien ini selalu memanggil Google; penyimpanan & pemakaian ulang hasil diatur AsistenAi::riwayat().
     */
    public function generate(string $prompt, array $opsi = []): string
    {
        if (! $this->aktif()) {
            throw new AiException('Fitur AI belum diaktifkan. Administrator perlu mengisi GEMINI_API_KEY_1 s.d. GEMINI_API_KEY_5 di .env.');
        }

        $body = array_filter([
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'systemInstruction' => isset($opsi['system']) ? ['parts' => [['text' => $opsi['system']]]] : null,
            'generationConfig' => array_filter([
                'temperature' => $opsi['temperature'] ?? 0.4,
                'responseMimeType' => ! empty($opsi['json']) ? 'application/json' : null,
                'responseSchema' => $opsi['schema'] ?? null,
            ], fn ($v) => $v !== null),
        ]);

        return $this->kirim($body);
    }

    /** Seperti generate(), tetapi jawaban di-decode dari JSON. */
    public function generateJson(string $prompt, array $schema, array $opsi = []): array
    {
        return self::bacaJson($this->generate($prompt, $opsi + ['json' => true, 'schema' => $schema]));
    }

    public static function bacaJson(string $teks): array
    {
        // beberapa model tetap membungkus JSON dengan ```json
        $data = json_decode(preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/', '', $teks), true);

        if (! is_array($data)) {
            throw new AiException('Jawaban AI tidak dapat dibaca. Silakan coba lagi.');
        }

        return $data;
    }

    /** Kondisi setiap key untuk halaman status / perintah ai:status (key disamarkan). */
    public function status(): array
    {
        return array_map(function ($key, $i) {
            $jeda = Cache::get($this->kunciJeda($key));

            return [
                'nomor' => $i + 1,
                'key' => '…'.substr($key, -4),
                'tersedia' => ! $jeda,
                'istirahat_sampai' => $jeda['sampai'] ?? null,
                'alasan' => $jeda['alasan'] ?? null,
                'pemakaian_hari_ini' => (int) Cache::get($this->kunciPakai($key), 0),
            ];
        }, $this->keys(), array_keys($this->keys()));
    }

    /** Lepaskan semua key dari masa istirahat (mis. setelah key diganti). */
    public function pulihkanSemua(): void
    {
        foreach ($this->keys() as $key) {
            Cache::forget($this->kunciJeda($key));
        }
    }

    private function kirim(array $body): string
    {
        $keys = $this->keys();
        $n = count($keys);

        // giliran berputar: setiap permintaan mulai dari key berikutnya
        $mulai = (int) Cache::get(self::CACHE.'giliran', 0);
        Cache::forever(self::CACHE.'giliran', ($mulai + 1) % $n);

        $dicoba = 0;
        $galat = null;
        for ($i = 0; $i < $n; $i++) {
            $key = $keys[($mulai + $i) % $n];
            if (Cache::has($this->kunciJeda($key))) {
                continue;
            }
            $dicoba++;

            try {
                $response = Http::timeout(config('gemini.timeout'))
                    ->acceptJson()
                    ->withHeaders(['x-goog-api-key' => $key])
                    ->post(rtrim(config('gemini.base_url'), '/').'/models/'.config('gemini.model').':generateContent', $body);
            } catch (ConnectionException $e) {
                $galat = 'Layanan AI tidak dapat dihubungi.';
                Log::warning('Gemini: koneksi gagal', ['key' => '…'.substr($key, -4), 'error' => $e->getMessage()]);

                continue;
            }

            if ($response->successful()) {
                $pakai = $this->kunciPakai($key);
                Cache::put($pakai, (int) Cache::get($pakai, 0) + 1, now()->addDays(2));

                return $this->teks($response);
            }

            $galat = $this->tanganiGalat($key, $response);
        }

        if ($dicoba === 0) {
            $pulih = collect($this->status())->pluck('istirahat_sampai')->filter()->min();

            throw new AiException('Semua API key AI sedang mencapai batas pemakaian'
                .($pulih ? ', tersedia lagi sekitar pukul '.CarbonImmutable::parse($pulih)->setTimezone(config('app.timezone'))->format('H:i') : '')
                .'. Silakan coba lagi nanti.');
        }

        throw new AiException($galat ?? 'Layanan AI gagal memproses permintaan.');
    }

    /**
     * Istirahatkan key sesuai jenis galat lalu kembalikan pesan untuk pengguna.
     * Galat yang bukan karena key (permintaan tidak valid) langsung dilempar tanpa mencoba key lain.
     */
    private function tanganiGalat(string $key, Response $response): string
    {
        $error = $response->json('error') ?? [];
        $pesan = (string) ($error['message'] ?? $response->body());
        $status = $response->status();
        $alasanKey = collect($error['details'] ?? [])->pluck('reason')->filter()->first();

        Log::warning('Gemini: permintaan gagal', ['key' => '…'.substr($key, -4), 'status' => $status, 'pesan' => mb_substr($pesan, 0, 300)]);

        if ($status === 429) {
            if ($this->kuotaHarian($error)) {
                $this->istirahatkan($key, CarbonImmutable::now(config('gemini.zona_reset_kuota'))->addDay()->startOfDay(), 'Kuota harian habis');
            } else {
                $this->istirahatkan($key, CarbonImmutable::now()->addSeconds($this->retryDelay($error) ?? config('gemini.jeda_rate_limit_detik')), 'Rate limit per menit');
            }

            return 'Batas pemakaian AI tercapai. Silakan coba lagi sebentar lagi.';
        }

        if (in_array($status, [401, 403], true) || $alasanKey === 'API_KEY_INVALID' || str_contains($pesan, 'API key')) {
            $this->istirahatkan($key, CarbonImmutable::now()->addMinutes(config('gemini.jeda_key_ditolak_menit')), 'Key ditolak: '.mb_substr($pesan, 0, 120));

            return 'API key AI ditolak oleh Google.';
        }

        if ($status >= 500) {
            // server Google sibuk/galat: coba key berikutnya tanpa mengistirahatkan key ini
            return 'Layanan AI sedang sibuk. Silakan coba lagi.';
        }

        throw new AiException('Permintaan ke layanan AI ditolak: '.mb_substr($pesan, 0, 200));
    }

    private function kuotaHarian(array $error): bool
    {
        return collect($error['details'] ?? [])
            ->flatMap(fn ($d) => $d['violations'] ?? [])
            ->contains(fn ($v) => str_contains((string) ($v['quotaId'] ?? ''), 'PerDay'));
    }

    private function retryDelay(array $error): ?int
    {
        $delay = collect($error['details'] ?? [])->pluck('retryDelay')->filter()->first();

        return $delay ? max(1, (int) ceil((float) $delay)) : null;
    }

    private function istirahatkan(string $key, CarbonImmutable $sampai, string $alasan): void
    {
        Cache::put($this->kunciJeda($key), ['sampai' => $sampai->toIso8601String(), 'alasan' => $alasan], $sampai);
    }

    private function teks(Response $response): string
    {
        $kandidat = $response->json('candidates.0') ?? [];
        $teks = collect($kandidat['content']['parts'] ?? [])
            ->reject(fn ($p) => ! empty($p['thought']))
            ->pluck('text')->filter()->implode('');

        if (trim($teks) === '') {
            $alasan = $kandidat['finishReason'] ?? $response->json('promptFeedback.blockReason');
            throw new AiException('AI tidak memberikan jawaban'.($alasan ? " ({$alasan})" : '').'. Silakan coba lagi.');
        }

        return trim($teks);
    }

    private function keys(): array
    {
        return array_values((array) config('gemini.keys'));
    }

    private function kunciJeda(string $key): string
    {
        return self::CACHE.'jeda.'.sha1($key);
    }

    private function kunciPakai(string $key): string
    {
        return self::CACHE.'pakai.'.sha1($key).'.'.now()->toDateString();
    }
}
