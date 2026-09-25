<?php

namespace App\Services\Siasn;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Login SSO SIASN (OpenID Connect authorization code + PKCE) ke Keycloak BKN.
 */
class SiasnSsoClient
{
    public function enabled(): bool
    {
        return config('siasn.oidc.enabled') && config('siasn.oidc.client_id');
    }

    public function redirectUrl(Request $request): string
    {
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(64);

        $request->session()->put('siasn_sso', compact('state', 'nonce', 'verifier'));

        return $this->endpoint('auth').'?'.http_build_query([
            'client_id' => config('siasn.oidc.client_id'),
            'redirect_uri' => $this->callbackUrl(),
            'response_type' => 'code',
            'scope' => config('siasn.oidc.scopes'),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Tukar authorization code menjadi token, ambil profil, dan kembalikan klaim identitas.
     *
     * @return array{nip:?string, email:?string, name:?string, sub:string, id_token:?string}
     */
    public function handleCallback(Request $request): array
    {
        $sesi = $request->session()->pull('siasn_sso');

        if (! $sesi || ! hash_equals($sesi['state'], (string) $request->query('state'))) {
            throw new SiasnException('Sesi login SSO tidak valid atau kedaluwarsa. Silakan ulangi.');
        }
        if ($request->query('error')) {
            throw new SiasnException('Login SSO dibatalkan: '.$request->query('error_description', $request->query('error')));
        }

        $token = Http::asForm()->timeout(config('siasn.timeout'))
            ->withOptions(['verify' => config('siasn.verify_ssl')])
            ->post($this->endpoint('token'), array_filter([
                'grant_type' => 'authorization_code',
                'code' => $request->query('code'),
                'redirect_uri' => $this->callbackUrl(),
                'client_id' => config('siasn.oidc.client_id'),
                'client_secret' => config('siasn.oidc.client_secret'),
                'code_verifier' => $sesi['verifier'],
            ]));

        if ($token->failed() || ! $token->json('access_token')) {
            throw new SiasnException('Gagal menukar kode SSO SIASN (HTTP '.$token->status().').');
        }

        $idClaims = $this->decodeJwt((string) $token->json('id_token'));
        if ($idClaims) {
            if (($idClaims['nonce'] ?? null) !== $sesi['nonce']) {
                throw new SiasnException('Nonce SSO tidak cocok.');
            }
            if (($idClaims['iss'] ?? null) !== rtrim(config('siasn.oidc.base_url'), '/')) {
                throw new SiasnException('Penerbit token SSO tidak dikenal.');
            }
        }

        // profil diambil langsung dari endpoint userinfo BKN melalui TLS
        $userinfo = Http::timeout(config('siasn.timeout'))
            ->withOptions(['verify' => config('siasn.verify_ssl')])
            ->withToken($token->json('access_token'))
            ->get($this->endpoint('userinfo'));

        if ($userinfo->failed()) {
            throw new SiasnException('Gagal mengambil profil SSO SIASN (HTTP '.$userinfo->status().').');
        }

        $claims = array_merge($idClaims, $userinfo->json() ?? []);
        $nipClaim = config('siasn.oidc.nip_claim');
        $nip = $claims[$nipClaim] ?? (preg_match('/^\d{18}$/', (string) ($claims['preferred_username'] ?? '')) ? $claims['preferred_username'] : null);

        return [
            'sub' => (string) ($claims['sub'] ?? ''),
            'nip' => $nip ? preg_replace('/\D/', '', (string) $nip) : null,
            'email' => $claims['email'] ?? null,
            'name' => $claims['name'] ?? null,
            'id_token' => $token->json('id_token'),
        ];
    }

    /** Cari user SIMONKEB yang terdaftar untuk identitas SSO ini. */
    public function findUser(array $identity): ?User
    {
        if ($identity['nip']) {
            $user = User::where('nip', $identity['nip'])->first();
            if ($user) {
                return $user;
            }
        }

        return $identity['email'] ? User::where('email', $identity['email'])->first() : null;
    }

    public function logoutUrl(?string $idToken): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        return $this->endpoint('logout').'?'.http_build_query(array_filter([
            'client_id' => config('siasn.oidc.client_id'),
            'id_token_hint' => $idToken,
            'post_logout_redirect_uri' => url('/login'),
        ]));
    }

    private function endpoint(string $name): string
    {
        return rtrim(config('siasn.oidc.base_url'), '/').'/protocol/openid-connect/'.$name;
    }

    private function callbackUrl(): string
    {
        $redirect = config('siasn.oidc.redirect');

        return str_starts_with($redirect, 'http') ? $redirect : url($redirect);
    }

    /** Baca payload JWT (tanpa verifikasi tanda tangan; identitas diverifikasi lewat userinfo). */
    private function decodeJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return [];
        }

        return json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) ?: [];
    }
}
