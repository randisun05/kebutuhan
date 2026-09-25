<?php

/*
|--------------------------------------------------------------------------
| Integrasi SIASN BKN
|--------------------------------------------------------------------------
| Akses web service SIASN memakai dua token:
|  - token APIM (WSO2) -> header "Authorization: Bearer ..." (grant client_credentials, basic auth)
|  - token SSO SIASN (Keycloak) -> header "Auth: Bearer ..." (grant password + client_id)
| Kredensial & whitelist IP diperoleh dari BKN (lihat Buku Petunjuk Integrasi Web Service SIASN).
*/

$mode = env('SIASN_MODE', 'training');

return [
    'enabled' => (bool) env('SIASN_ENABLED', false),

    // production / training
    'mode' => $mode,

    'base_url' => env('SIASN_BASE_URL', $mode === 'production'
        ? 'https://apimws.bkn.go.id:8243/apisiasn/1.0'
        : 'https://training-apimws.bkn.go.id:8243/apisiasn/1.0'),

    'apim' => [
        'url' => env('SIASN_APIM_URL', $mode === 'production'
            ? 'https://apimws.bkn.go.id/oauth2/token'
            : 'https://training-apimws.bkn.go.id/oauth2/token'),
        'username' => env('SIASN_APIM_USERNAME'),
        'password' => env('SIASN_APIM_PASSWORD'),
        'ttl' => (int) env('SIASN_APIM_TOKEN_AGE', 3600),
    ],

    'sso' => [
        'url' => env('SIASN_SSO_URL', $mode === 'production'
            ? 'https://sso-siasn.bkn.go.id/auth/realms/public-siasn/protocol/openid-connect/token'
            : 'https://iam-siasn.bkn.go.id/auth/realms/public-siasn/protocol/openid-connect/token'),
        'client_id' => env('SIASN_SSO_CLIENT_ID'),
        'username' => env('SIASN_SSO_USERNAME'),
        'password' => env('SIASN_SSO_PASSWORD'),
        // alternatif: token SSO yang sudah didapat manual
        'access_token' => env('SIASN_SSO_ACCESS_TOKEN'),
        'ttl' => (int) env('SIASN_SSO_TOKEN_AGE', 43200),
    ],

    'endpoints' => [
        'data_utama' => env('SIASN_ENDPOINT_DATA_UTAMA', '/pns/data-utama/{nip}'),
        'ref_unor' => env('SIASN_ENDPOINT_REF_UNOR', '/referensi/ref-unor'),
    ],

    'timeout' => (int) env('SIASN_REQUEST_TIMEOUT', 60),
    'verify_ssl' => (bool) env('SIASN_VERIFY_SSL', true),

    // sinkronisasi pegawai otomatis setiap malam untuk semua instansi yang memiliki ID SIASN
    'scheduled' => (bool) env('SIASN_SYNC_TERJADWAL', false),
];
