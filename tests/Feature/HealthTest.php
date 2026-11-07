<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_dapat_diakses_tanpa_login_dan_melaporkan_scheduler(): void
    {
        $this->getJson('/health')->assertOk()
            ->assertJsonPath('database', 'ok')->assertJsonPath('scheduler', 'belum_berjalan')->assertJsonPath('status', 'perlu_perhatian')
            ->assertJsonMissingPath('antrean.gagal');

        Cache::put('scheduler:detak', now());
        $this->getJson('/health')->assertOk()->assertJsonPath('scheduler', 'ok')->assertJsonPath('status', 'ok');

        Cache::put('scheduler:detak', now()->subMinutes(20));
        $this->getJson('/health')->assertJsonPath('scheduler', 'terlambat');
    }

    public function test_health_tidak_membocorkan_data_sensitif(): void
    {
        $isi = $this->getJson('/health')->getContent();
        foreach (['password', 'APP_KEY', 'siasn', '@'] as $kata) {
            $this->assertStringNotContainsString($kata, $isi);
        }
    }
}
