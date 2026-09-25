<?php

return [
    // kirim notifikasi alur usulan juga lewat email (selain notifikasi di aplikasi)
    'notifikasi_email' => (bool) env('NOTIFIKASI_EMAIL', false),

    // peran yang wajib mengaktifkan autentikasi dua faktor (pisahkan koma), contoh: admin,verifikator_bkn
    'wajib_2fa' => array_values(array_filter(array_map('trim', explode(',', (string) env('WAJIB_2FA_PERAN', ''))))),
];
