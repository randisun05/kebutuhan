<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Wajibkan autentikasi dua faktor untuk peran yang tercantum pada WAJIB_2FA_PERAN.
 * User diarahkan ke halaman keamanan akun sampai 2FA aktif dan terkonfirmasi.
 */
class EnsureTwoFactorForRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && in_array($user->role?->value, config('simonkeb.wajib_2fa'), true) && ! $user->two_factor_confirmed_at
            && ! $request->routeIs('akun', 'logout', 'password.confirm', 'password.confirmation', 'two-factor.*', 'user-password.update')
            && ! $request->is('user/*')) {
            return redirect()->route('akun')->with('warning', 'Peran Anda wajib mengaktifkan autentikasi dua faktor (2FA) sebelum melanjutkan.');
        }

        return $next($request);
    }
}
