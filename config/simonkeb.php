<?php

return [
    // kirim notifikasi alur usulan juga lewat email (selain notifikasi di aplikasi)
    'notifikasi_email' => (bool) env('NOTIFIKASI_EMAIL', false),

    // peran yang wajib mengaktifkan autentikasi dua faktor (pisahkan koma), contoh: admin,verifikator_bkn
    'wajib_2fa' => array_values(array_filter(array_map('trim', explode(',', (string) env('WAJIB_2FA_PERAN', ''))))),

    // ambang batas sistem peringatan dini (early warning)
    'peringatan' => [
        'stagnan_bulan' => (int) env('PERINGATAN_STAGNAN_BULAN', 6),
        'data_usang_bulan' => (int) env('PERINGATAN_DATA_USANG_BULAN', 6),
        'gemuk_persen' => (int) env('PERINGATAN_GEMUK_PERSEN', 130),
        'kritis_persen' => (int) env('PERINGATAN_KEKURANGAN_KRITIS_PERSEN', 50),
        'usulan_tertahan_hari' => (int) env('PERINGATAN_USULAN_TERTAHAN_HARI', 14),
        'usulan_dikembalikan_hari' => (int) env('PERINGATAN_USULAN_DIKEMBALIKAN_HARI', 30),
        'formasi_belum_terisi_bulan' => (int) env('PERINGATAN_FORMASI_BULAN', 12),
        'abk_maks_umur_tahun' => (int) env('PERINGATAN_ABK_UMUR_TAHUN', 2),
        'pensiun_bulan' => (int) env('PERINGATAN_PENSIUN_BULAN', 12),
    ],
];
