<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use App\Services\Siasn\SiasnSsoClient;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        // halaman autentikasi (Inertia)
        Fortify::loginView(fn () => inertia('Auth/Login', [
            'ssoSiasn' => app(SiasnSsoClient::class)->enabled(),
        ]));

        // keluar juga dari sesi SSO SIASN bila login lewat SSO
        Event::listen(Logout::class, function () {
            if (request()->hasSession() && request()->session()->get('login_via') === 'sso_siasn') {
                request()->attributes->set('siasn_id_token', request()->session()->get('siasn_id_token') ?: 'sso');
            }
        });
        $this->app->singleton(LogoutResponse::class, fn () => new class implements LogoutResponse
        {
            public function toResponse($request)
            {
                $token = $request->attributes->get('siasn_id_token');
                $url = $token ? app(SiasnSsoClient::class)->logoutUrl($token === 'sso' ? null : $token) : null;

                return $url ? Inertia::location($url) : redirect('/login');
            }
        });
        Fortify::requestPasswordResetLinkView(fn () => inertia('Auth/ForgotPassword'));
        Fortify::resetPasswordView(fn (Request $request) => inertia('Auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->query('email'),
        ]));
        Fortify::twoFactorChallengeView(fn () => inertia('Auth/TwoFactorChallenge'));
        Fortify::confirmPasswordView(fn () => inertia('Auth/ConfirmPassword'));

        // hanya akun aktif yang dapat masuk
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->input('email'))->first();

            return $user && $user->is_active && Hash::check((string) $request->input('password'), $user->password) ? $user : null;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

    }
}
