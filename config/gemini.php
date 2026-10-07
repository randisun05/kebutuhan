<?php

// Daftar API key Gemini. Boleh diisi lewat GEMINI_API_KEYS (pisahkan koma) dan/atau
// GEMINI_API_KEY_1 s.d. GEMINI_API_KEY_5. Key dipakai bergiliran; key yang kena batas
// kuota/rate limit diistirahatkan sementara lalu dipakai lagi setelah pulih.
$keys = array_merge(
    explode(',', (string) env('GEMINI_API_KEYS', '')),
    array_map(fn ($i) => (string) env("GEMINI_API_KEY_{$i}", ''), range(1, 5)),
);

return [
    'keys' => array_values(array_unique(array_filter(array_map('trim', $keys)))),

    // matikan fitur AI tanpa menghapus key
    'enabled' => (bool) env('GEMINI_ENABLED', true),

    // alias "latest" selalu menunjuk model Flash terbaru yang tersedia di tier gratis
    'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    'timeout' => (int) env('GEMINI_TIMEOUT', 60),

    // lama key diistirahatkan bila kena rate limit per menit (bila Google tidak memberi retryDelay)
    'jeda_rate_limit_detik' => (int) env('GEMINI_JEDA_RATE_LIMIT', 60),
    // lama key diistirahatkan bila ditolak (key salah/dicabut/API belum aktif)
    'jeda_key_ditolak_menit' => (int) env('GEMINI_JEDA_KEY_DITOLAK', 60),
    // kuota harian tier gratis direset tengah malam waktu Pasifik
    'zona_reset_kuota' => 'America/Los_Angeles',
];
