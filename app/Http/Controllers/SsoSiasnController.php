<?php

namespace App\Http\Controllers;

use App\Services\Siasn\SiasnException;
use App\Services\Siasn\SiasnSsoClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SsoSiasnController extends Controller
{
    public function redirect(Request $request, SiasnSsoClient $sso)
    {
        abort_unless($sso->enabled(), 404);

        return redirect()->away($sso->redirectUrl($request));
    }

    public function callback(Request $request, SiasnSsoClient $sso)
    {
        abort_unless($sso->enabled(), 404);

        try {
            $identity = $sso->handleCallback($request);
        } catch (SiasnException $e) {
            return redirect()->route('login')->withErrors(['email' => $e->getMessage()]);
        }

        $user = $sso->findUser($identity);
        if (! $user || ! $user->is_active) {
            activity('keamanan')->withProperties(['nip' => $identity['nip'], 'email' => $identity['email']])->log('login SSO SIASN ditolak: akun tidak terdaftar/nonaktif');

            return redirect()->route('login')->withErrors([
                'email' => 'Akun SSO SIASN '.($identity['nip'] ?? $identity['email']).' belum terdaftar atau nonaktif di SIMONKEB. Hubungi administrator.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('login_via', 'sso_siasn');
        $request->session()->put('siasn_id_token', $identity['id_token']);

        activity('keamanan')->causedBy($user)->performedOn($user)->log('login SSO SIASN');

        return redirect()->intended(route('dashboard'));
    }
}
