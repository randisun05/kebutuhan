<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_berulang_dibatasi_dengan_pesan_di_formulir(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'tidak-ada@simonkeb.test', 'password' => 'salah'])
                ->assertSessionHasErrors('email');
        }

        $respons = $this->post('/login', ['email' => 'tidak-ada@simonkeb.test', 'password' => 'salah']);

        $respons->assertRedirect();
        $respons->assertSessionHasErrors('email');
        $this->assertStringContainsString('Terlalu banyak percobaan masuk', session('errors')->first('email'));
        $this->assertGuest();
    }
}
