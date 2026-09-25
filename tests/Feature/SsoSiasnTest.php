<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsKebutuhanData;
use Tests\TestCase;

class SsoSiasnTest extends TestCase
{
    use BuildsKebutuhanData, RefreshDatabase;

    private const ISS = 'https://sso.test/auth/realms/public-siasn';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'siasn.oidc.enabled' => true,
            'siasn.oidc.base_url' => self::ISS,
            'siasn.oidc.client_id' => 'simonkeb',
            'siasn.oidc.client_secret' => 'rahasia',
            'siasn.oidc.nip_claim' => 'nip',
        ]);
    }

    private function jwt(array $claims): string
    {
        $b64 = fn ($d) => rtrim(strtr(base64_encode(json_encode($d)), '+/', '-_'), '=');

        return $b64(['alg' => 'none']).'.'.$b64($claims).'.sig';
    }

    /** Mulai login, lalu kembalikan state & nonce dari sesi. */
    private function mulai(): array
    {
        $res = $this->get('/auth/siasn/redirect');
        $res->assertRedirect();
        $url = $res->headers->get('Location');
        $this->assertStringStartsWith(self::ISS.'/protocol/openid-connect/auth?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame('S256', $q['code_challenge_method']);

        return [$q['state'], $q['nonce']];
    }

    private function fakeIdp(string $nonce, array $userinfo): void
    {
        Http::fake([
            self::ISS.'/protocol/openid-connect/token' => Http::response([
                'access_token' => 'AT', 'id_token' => $this->jwt(['iss' => self::ISS, 'nonce' => $nonce, 'sub' => 'u-1']),
            ]),
            self::ISS.'/protocol/openid-connect/userinfo' => Http::response($userinfo),
        ]);
    }

    public function test_login_sso_dengan_nip_terdaftar(): void
    {
        $instansi = $this->instansi();
        $operator = $this->user(Role::OperatorInstansi, $instansi);
        $operator->update(['nip' => '198501012010011001']);

        [$state, $nonce] = $this->mulai();
        $this->fakeIdp($nonce, ['sub' => 'u-1', 'nip' => '198501012010011001', 'name' => 'Operator']);

        $this->get("/auth/siasn/callback?code=abc&state={$state}")->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($operator);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/token') && $r['grant_type'] === 'authorization_code' && isset($r['code_verifier']));
    }

    public function test_sso_melewati_2fa_lokal_yang_diwajibkan(): void
    {
        config(['simonkeb.wajib_2fa' => ['admin']]);
        $admin = $this->user(Role::Admin);
        $admin->update(['nip' => '198501012010011002']);

        [$state, $nonce] = $this->mulai();
        $this->fakeIdp($nonce, ['sub' => 'u-1', 'nip' => '198501012010011002']);
        $this->get("/auth/siasn/callback?code=abc&state={$state}");

        $this->get('/dashboard')->assertOk();
    }

    public function test_akun_sso_tidak_terdaftar_ditolak(): void
    {
        [$state, $nonce] = $this->mulai();
        $this->fakeIdp($nonce, ['sub' => 'u-9', 'nip' => '199901012020011009']);

        $this->get("/auth/siasn/callback?code=abc&state={$state}")->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_state_atau_nonce_salah_ditolak(): void
    {
        $user = $this->user(Role::Admin);
        $user->update(['nip' => '198501012010011003']);

        $this->mulai();
        $this->get('/auth/siasn/callback?code=abc&state=palsu')->assertSessionHasErrors('email');

        [$state] = $this->mulai();
        $this->fakeIdp('nonce-lain', ['sub' => 'u-1', 'nip' => '198501012010011003']);
        $this->get("/auth/siasn/callback?code=abc&state={$state}")->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_tombol_sso_tidak_aktif_bila_belum_dikonfigurasi(): void
    {
        config(['siasn.oidc.enabled' => false]);
        $this->get('/auth/siasn/redirect')->assertNotFound();
        $this->get('/login')->assertInertia(fn ($p) => $p->where('ssoSiasn', false));
    }
}
