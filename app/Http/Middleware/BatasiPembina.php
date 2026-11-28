<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengguna berperan pembina hanya boleh memakai modul instansi pembina (rekomendasi JF, master JF, profil impor),
 * panduan, dan pengelolaan akunnya. Seluruh modul operasional lain (monitoring, ABK, usulan, pegawai, ...) ditolak
 * di server walau alamatnya diketik langsung. Halaman utama dialihkan ke modul rekomendasi.
 */
class BatasiPembina
{
    /** Awalan path yang boleh diakses pengguna pembina. */
    private const BOLEH = [
        'rekomendasi-jf', 'perbandingan-jf', 'jf-master', 'impor-profil', 'panduan', 'akun', 'notifikasi', 'logout', 'user', 'two-factor-challenge',
        'confirm-password', 'forgot-password', 'reset-password', 'email', 'health', 'up', 'img', 'build',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isPembina()) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        if ($path === '' || $path === 'dashboard') {
            return redirect('/rekomendasi-jf');
        }

        $awal = explode('/', $path)[0];
        abort_unless(in_array($awal, self::BOLEH, true), 403, 'Modul ini bukan bagian dari hak instansi pembina.');

        return $next($request);
    }
}
