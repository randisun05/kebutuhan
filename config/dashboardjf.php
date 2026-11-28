<?php

/*
|--------------------------------------------------------------------------
| API Dashboard (sumber data JF / bezetting kondisi kini)
|--------------------------------------------------------------------------
| Ditarik oleh `php artisan jf:tarik-dashboard` ke tabel kebutuhan_fakta untuk sumber berasal "dashboard".
| Spesifikasi API Dashboard belum final, sehingga alamat, autentikasi, nama parameter, dan nama field respons dapat diatur di sini
| (lewat .env) tanpa mengubah kode. Kontrak yang diharapkan dan cara menghubungkan: docs/API-DASHBOARD.md.
*/

return [
    'enabled' => (bool) env('DASHBOARD_JF_ENABLED', false),
    'scheduled' => (bool) env('DASHBOARD_JF_TERJADWAL', false),

    'base_url' => env('DASHBOARD_JF_BASE_URL'),
    'endpoint' => env('DASHBOARD_JF_ENDPOINT', '/jf/bezetting'),
    'timeout' => (int) env('DASHBOARD_JF_TIMEOUT', 30),
    'verify_ssl' => (bool) env('DASHBOARD_JF_VERIFY_SSL', true),

    // autentikasi: header berisi "<skema> <token>" (skema kosong = token apa adanya, mis. untuk X-Api-Key)
    'token' => env('DASHBOARD_JF_TOKEN'),
    'auth_header' => env('DASHBOARD_JF_AUTH_HEADER', 'Authorization'),
    'auth_skema' => env('DASHBOARD_JF_AUTH_SKEMA', 'Bearer'),

    // nama parameter query yang dikirim
    'param' => [
        'pembina' => env('DASHBOARD_JF_PARAM_PEMBINA', 'pembina'),
        'per' => env('DASHBOARD_JF_PARAM_PER', 'per'),
        'halaman' => env('DASHBOARD_JF_PARAM_HALAMAN', 'page'),
        'ukuran' => env('DASHBOARD_JF_PARAM_UKURAN', 'per_page'),
    ],
    'ukuran_halaman' => (int) env('DASHBOARD_JF_UKURAN_HALAMAN', 500),

    // nama field pada respons JSON (notasi titik untuk bersarang)
    'respons' => [
        'data' => env('DASHBOARD_JF_FIELD_DATA', 'data'),
        'halaman_terakhir' => env('DASHBOARD_JF_FIELD_HALAMAN_TERAKHIR', 'meta.last_page'),
        'per' => env('DASHBOARD_JF_FIELD_PER', 'per'),
        'instansi_id' => env('DASHBOARD_JF_FIELD_INSTANSI_ID', 'instansi_siasn_id'),
        'instansi_kode' => env('DASHBOARD_JF_FIELD_INSTANSI_KODE', 'instansi_kode'),
        'instansi_nama' => env('DASHBOARD_JF_FIELD_INSTANSI_NAMA', 'instansi_nama'),
        'jabatan' => env('DASHBOARD_JF_FIELD_JABATAN', 'jabatan'),
        'jenjang' => env('DASHBOARD_JF_FIELD_JENJANG', 'jenjang'),
        'jumlah' => env('DASHBOARD_JF_FIELD_JUMLAH', 'jumlah'),
    ],
];
