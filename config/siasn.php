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

    /*
    | Login SSO SIASN (OpenID Connect, Keycloak realm public-siasn).
    | Aplikasi harus didaftarkan sebagai client ke BKN (client_id, client_secret, redirect URI).
    | User SIMONKEB dicocokkan berdasarkan NIP (users.nip) lalu email.
    */
    'oidc' => [
        'enabled' => (bool) env('SIASN_SSO_LOGIN', false),
        'base_url' => env('SIASN_OIDC_URL', $mode === 'production'
            ? 'https://sso-siasn.bkn.go.id/auth/realms/public-siasn'
            : 'https://iam-siasn.bkn.go.id/auth/realms/public-siasn'),
        'client_id' => env('SIASN_OIDC_CLIENT_ID'),
        'client_secret' => env('SIASN_OIDC_CLIENT_SECRET'),
        'redirect' => env('SIASN_OIDC_REDIRECT') ?: '/auth/siasn/callback',
        'scopes' => env('SIASN_OIDC_SCOPES', 'openid profile email'),
        // claim yang berisi NIP pada token/userinfo SIASN
        'nip_claim' => env('SIASN_OIDC_NIP_CLAIM', 'nip'),
        // SSO BKN sudah memakai MFA, sehingga 2FA lokal tidak diminta lagi
        'skip_local_2fa' => (bool) env('SIASN_OIDC_SKIP_2FA', true),
    ],

    // sinkronisasi pegawai otomatis setiap malam untuk semua instansi yang memiliki ID SIASN
    'scheduled' => (bool) env('SIASN_SYNC_TERJADWAL', false),
];
