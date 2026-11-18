<?php

/*
|--------------------------------------------------------------------------
| API JF instansi pembina BKN (produk "pusbin")
|--------------------------------------------------------------------------
| Disalin dari proyek ujikom-pusbin. Memakai dua token seperti SIASN:
|  - token APIM (client_credentials, basic auth) -> header "Authorization: Bearer"
|  - token SSO SIASN -> header "Auth: Bearer". grant password biasanya ditolak BKN, sehingga
|    token manual (dari sesi SIASN yang sudah login) di JFPEMBINA_AUTH_TOKEN adalah jalur utama;
|    token ini kedaluwarsa dalam hitungan jam dan harus ditempel ulang.
| Kredensial APIM/SSO memakai nilai SIASN_* bila JFPEMBINA_* tidak diisi.
*/

return [
    'enabled' => (bool) env('JFPEMBINA_ENABLED', false),

    'base_url' => env('JFPEMBINA_BASE_URL', 'https://apimws.bkn.go.id:8243/pusbin/1'),

    'apim' => [
        'url' => env('JFPEMBINA_APIM_URL', 'https://apimws.bkn.go.id/oauth2/token'),
        'username' => env('JFPEMBINA_CLIENT_ID', env('SIASN_APIM_USERNAME')),
        'password' => env('JFPEMBINA_CLIENT_SECRET', env('SIASN_APIM_PASSWORD')),
        'ttl' => (int) env('SIASN_APIM_TOKEN_AGE', 3600),
    ],

    'sso' => [
        'url' => env('JFPEMBINA_SSO_URL', 'https://sso-siasn.bkn.go.id/auth/realms/public-siasn/protocol/openid-connect/token'),
        'client_id' => env('JFPEMBINA_SSO_CLIENT_ID', env('SIASN_SSO_CLIENT_ID', 'internalpusbinjf')),
        'username' => env('JFPEMBINA_SSO_USERNAME', env('SIASN_SSO_USERNAME')),
        'password' => env('JFPEMBINA_SSO_PASSWORD', env('SIASN_SSO_PASSWORD')),
        'access_token' => env('JFPEMBINA_AUTH_TOKEN', env('SIASN_SSO_ACCESS_TOKEN')),
        'ttl' => (int) env('SIASN_SSO_TOKEN_AGE', 43200),
    ],

    'endpoints' => [
        // wajib: instansi, limit (maks 100), offset. Tiap baris hanya {id, nip, nama}.
        'list' => env('JFPEMBINA_ENDPOINT_LIST', '/pns/list-asn-pembinajf'),
        'detail' => env('JFPEMBINA_ENDPOINT_DETAIL', '/pns/data-utama/{nip}'),
    ],

    // Gateway BKN lambat (~8 dtk/NIP) dan tidak stabil; naikkan konkurensi dengan hati-hati.
    'timeout' => (int) env('JFPEMBINA_TIMEOUT', 40),
    'verify_ssl' => (bool) env('JFPEMBINA_VERIFY_SSL', true),
    'concurrency' => (int) env('JFPEMBINA_CONCURRENCY', 5),
    'halaman' => 100,
    'chunk' => 40,
    'max_attempts' => 5,
    'detail_stale_days' => 30,
    'stale_minutes' => 15,

    // sinkronisasi mingguan otomatis (Minggu dini hari)
    'scheduled' => (bool) env('JFPEMBINA_SYNC_TERJADWAL', false),
];
